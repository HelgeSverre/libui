<?php

declare(strict_types=1);

/**
 * Code generator: libui-ng `ui.h`  ->  cleaned FFI header + typed OO classes.
 *
 * Run via:  composer regen   (or: php tools/generate.php)
 *
 * Pipeline:
 *   cleanHeader()      ui.h            -> src/Native/libui.gen.h
 *   parseFunctions()   gen.h           -> [{name, ret, params}]
 *   parseEnums()       gen.h           -> [enum => {case => value}]
 *   emit*()            the above       -> src/Generated/**
 *
 * The header naming is ~98% regular (`ui<Type><Verb>(<Type>* self, ...)`), so
 * the bulk is mechanical; tools/annotations.php carries the small irregular set.
 *
 * @phpstan-type DocData array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}
 * @phpstan-type ParsedParam array{raw: string, isCallback: bool, type: string, name: string}
 * @phpstan-type ParsedFunction array{name: string, ret: string, params: list<ParsedParam>}
 * @phpstan-type Classification array{kind: string, name?: string, scalarOut?: bool}
 * @phpstan-type ConstructorConfig array{primary?: string|null, factories?: array<string, string>}
 * @phpstan-type GeneratorAnnotations array{skip_types: list<string>, constructors: array<string, ConstructorConfig>, bool_funcs: list<string>, flag_enums: list<string>, facade_funcs: list<string>, doc_overrides: array<string, string>, deviating_callbacks: array<string, string>}
 * @phpstan-type GeneratorContext array{enums: array<string, array<string, int>>, types: list<string>, generatedTypes: list<string>, functions: array<string, ParsedFunction>, annotations: GeneratorAnnotations, docs: array<string, DocData>}
 */

const ROOT = __DIR__ . '/..';

const UI_H = ROOT . '/third_party/libui-ng/ui.h';

const GEN_H = ROOT . '/src/Native/libui.gen.h';

const GEN_DIR = ROOT . '/src/Generated';

// --- pipe helpers -----------------------------------------------------------

function preg(string $pattern, string $replacement): Closure
{
    return fn (string $subject): string => preg_replace($pattern, $replacement, $subject);
}

function shouldDropLine(string $trimmed): bool
{
    return $trimmed !== '' && $trimmed[0] === '#' || preg_match('/^extern\s+"C"\s*\{?$/', $trimmed) || $trimmed === '}';
}

function dropNoiseLines(string $source): string
{
    $lines = array_values(array_filter(
        explode("\n", $source),
        fn (string $line): bool => ! shouldDropLine(trim($line)),
    ));

    return implode("\n", $lines);
}

function prependGenerationNotice(string $content): string
{
    return "// GENERATED from libui-ng ui.h by tools/generate.php — DO NOT EDIT.\n// Re-run `composer regen` to regenerate.\n\n{$content}\n";
}

// =============================================================================
// 1. Header cleaning  (Gate 0)
// =============================================================================

/** Transform libui-ng's ui.h into a header PHP's FFI::cdef() can parse. */
function cleanHeader(string $source): string
{
    return $source
        |> preg('/_UI_ENUM\(\s*(\w+)\s*\)/', 'typedef unsigned int $1; enum')
        |> preg('/\b_UI_EXTERN\b[ \t]*/', '')
        |> preg('#/\*.*?\*/#s', '')
        |> preg('#//[^\n]*#', '')
        |> preg(
            '/^[ \t]*struct[ \t]+tm[ \t]*;[ \t]*$/m',
            'struct tm { int tm_sec, tm_min, tm_hour, tm_mday, tm_mon, tm_year, tm_wday, tm_yday, tm_isdst; };',
        )
        |> dropNoiseLines(...)
        |> trim(...)
        |> preg("/\n{3,}/", "\n\n")
        |> prependGenerationNotice(...);
}

// =============================================================================
// 1b. Doc harvesting  (human-readable summaries from RAW ui.h)
// =============================================================================

/**
 * Harvest concise PHPDoc data for every documented
 * `_UI_EXTERN ... uiName(` function and `_UI_ENUM(uiName)` enum, taken from the
 * comment block IMMEDIATELY preceding the declaration in the RAW ui.h.
 *
 * Two comment styles are supported (libui mixes both):
 *   - doxygen `/** ... *\/` blocks   -> first prose line, stop at the first @tag
 *   - older `// ...` line comments   -> joined leading lines, first sentence
 *
 * Returns a map [uiName => structured doc data]. Names with no usable signal are
 * omitted, so callers can fall back to a bare `@see`. Text is single-line,
 * whitespace-collapsed, length-capped, and stripped of any `*\/` sequence so it
 * can never close the emitted docblock early.
 *
 * Harvested from the RAW header on purpose: cleanHeader() deletes all comments.
 *
 * @return array<string, array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}>
 */
function harvestDocs(string $rawHeader): array
{
    $lines = explode("\n", $rawHeader);
    $docs = [];

    foreach ($lines as $index => $line) {
        if (preg_match('/^\s*_UI_EXTERN\b.*?\b(ui[A-Za-z0-9_]+)\s*\(/', $line, $matches)) {
            $name = $matches[1];
        } elseif (preg_match('/^\s*_UI_ENUM\s*\(\s*(ui[A-Za-z0-9_]+)\s*\)/', $line, $matches)) {
            $name = $matches[1];
        } else {
            continue;
        }
        if (isset($docs[$name])) {
            continue;
        }

        $doc = extractDoc($lines, $index);
        if (docHasSignal($doc)) {
            $docs[$name] = $doc;
        }
    }

    return $docs;
}

/**
 * Overlay hand-authored summaries onto harvested docs. Used where the header's
 * comments are wrong (e.g. rotated DateTimePicker constructor summaries).
 *
 * @param array<string, DocData> $docs
 * @param array<string, string> $overrides uiName => summary
 * @return array<string, DocData>
 */
function applyDocOverrides(array $docs, array $overrides): array
{
    foreach ($overrides as $name => $summary) {
        $doc = $docs[$name] ?? emptyDoc();
        $doc['summary'] = sanitizeSummary($summary);
        $docs[$name] = $doc;
    }

    return $docs;
}

/** @return array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>} */
function emptyDoc(): array
{
    return ['summary' => '', 'params' => [], 'return' => '', 'notes' => [], 'warnings' => []];
}

/** @param array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>} $doc */
function docHasSignal(array $doc): bool
{
    return $doc['summary'] !== '' || $doc['params'] !== [] || $doc['return'] !== '' || $doc['notes'] !== [] || $doc['warnings'] !== [];
}

/**
 * Given the raw header lines and the index of a declaration, look at the
 * comment block on the lines immediately above it.
 *
 * @param list<string> $lines
 * @return array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}
 */
function extractDoc(array $lines, int $declarationIndex): array
{
    $previousIndex = $declarationIndex - 1;
    if ($previousIndex < 0) {
        return emptyDoc();
    }

    $previousLine = trim($lines[$previousIndex]);
    if ($previousLine === '') {
        return emptyDoc();
    }

    // --- doxygen block: the line above ends with `*/` ------------------------
    if (str_ends_with($previousLine, '*/')) {
        $start = null;
        for ($searchIndex = $previousIndex; $searchIndex >= 0; $searchIndex--) {
            if (str_contains($lines[$searchIndex], '/*')) {
                $start = $searchIndex;
                break;
            }
            $trimmed = trim($lines[$searchIndex]);
            if ($trimmed !== '' && $trimmed[0] !== '*' && ! str_ends_with($trimmed, '*/')) {
                $start = null;
                break;
            }
        }
        if ($start === null) {
            return emptyDoc();
        }

        return docFromBlock(array_slice($lines, $start, $previousIndex - $start + 1));
    }

    // --- line-comment run: the line above starts with `//` -------------------
    if (str_starts_with($previousLine, '//')) {
        $block = [];
        for ($searchIndex = $previousIndex; $searchIndex >= 0; $searchIndex--) {
            $trimmed = trim($lines[$searchIndex]);
            if (! str_starts_with($trimmed, '//')) {
                break;
            }
            $block[] = $trimmed;
        }

        return docFromLineComments(array_reverse($block));
    }

    return emptyDoc();
}

/**
 * Parse a doxygen `/** ... *\/` block into concise PHPDoc data.
 *
 * @param list<string> $blockLines
 * @return array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}
 */
function docFromBlock(array $blockLines): array
{
    $doc = emptyDoc();

    // Accumulate the active tag so continuation lines (those with no leading @)
    // extend it instead of being dropped or mis-attributed to the summary. Each
    // active entry is ['kind' => 'param'|'return'|'note'|'warning', 'key' => ?string, 'text' => string].
    $current = null;
    $summaryParts = [];
    $seenTag = false;

    $flush = static function (?array &$current, array &$doc): void {
        if ($current === null) {
            return;
        }
        $description = sanitizeDocText($current['text']);
        if ($description !== '') {
            switch ($current['kind']) {
                case 'param':
                    $doc['params'][$current['key']] = $description;
                    break;
                case 'return':
                    $doc['return'] = $description;
                    break;
                case 'note':
                    $doc['notes'][] = $description;
                    break;
                case 'warning':
                    $doc['warnings'][] = $description;
                    break;
            }
        }
        $current = null;
    };

    foreach ($blockLines as $raw) {
        $text = trim($raw);
        $text = preg_replace('#^/\*\*?#', '', $text);
        $text = preg_replace('#\*/\s*$#', '', $text);
        $text = preg_replace('/^\*\s?/', '', $text);
        $text = trim($text);
        if ($text === '') {
            continue;
        }

        if (preg_match('/^@param\s+([A-Za-z_]\w*)\s*(.*)$/', $text, $matches)) {
            $flush($current, $doc);
            $seenTag = true;
            $current = ['kind' => 'param', 'key' => $matches[1], 'text' => $matches[2]];
            continue;
        }
        if (preg_match('/^@returns?\s+(.*)$/', $text, $matches)) {
            $flush($current, $doc);
            $seenTag = true;
            $current = ['kind' => 'return', 'key' => null, 'text' => $matches[1]];
            continue;
        }
        if (preg_match('/^@note\s+(.*)$/', $text, $matches)) {
            $flush($current, $doc);
            $seenTag = true;
            $current = ['kind' => 'note', 'key' => null, 'text' => $matches[1]];
            continue;
        }
        if (preg_match('/^@warning\s+(.*)$/', $text, $matches)) {
            $flush($current, $doc);
            $seenTag = true;
            $current = ['kind' => 'warning', 'key' => null, 'text' => $matches[1]];
            continue;
        }
        if ($text[0] === '@') {
            // An unrecognised tag ends any active accumulation.
            $flush($current, $doc);
            $seenTag = true;
            continue;
        }

        // A continuation line: extend the active tag, or build the leading summary.
        if ($current !== null) {
            $current['text'] .= ' ' . $text;
        } elseif (! $seenTag) {
            $summaryParts[] = $text;
        }
    }

    $flush($current, $doc);

    if ($summaryParts !== []) {
        $doc['summary'] = sanitizeSummary(implode(' ', $summaryParts));
    }

    return $doc;
}

/**
 * Extract the first sentence from a run of `// ...` line comments.
 *
 * @param list<string> $commentLines
 * @return array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}
 */
function docFromLineComments(array $commentLines): array
{
    $doc = emptyDoc();
    $parts = [];
    foreach ($commentLines as $raw) {
        $text = ltrim($raw, '/');
        $text = trim($text);
        if ($text === '' || preg_match('/^(TODO|FIXME|XXX|HACK)\b/', $text)) {
            if ($parts !== []) {
                break;
            }
            continue;
        }
        $parts[] = $text;
    }
    if ($parts === []) {
        return $doc;
    }

    $joined = implode(' ', $parts);
    if (preg_match('/^(.*?[.!?])(?:\s|$)/s', $joined, $matches)) {
        $joined = $matches[1];
    }
    $doc['summary'] = sanitizeSummary($joined);

    return $doc;
}

/** Collapse whitespace, strip docblock-breakers, and cap the length. */
function sanitizeSummary(string $text): string
{
    $text = sanitizeDocText($text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) > 120) {
        $text = rtrim(mb_substr($text, 0, 117)) . '...';
    }

    return $text;
}

/** Keep useful C docs while dropping FFI/C ownership boilerplate. */
function sanitizeDocText(string $text): string
{
    $text = str_replace(['*/', '\n'], ['', ' '], $text);
    $text = preg_replace_callback(
        '/@p\s+([A-Za-z_]\w*)/',
        static fn (array $matches): string => '$' . $matches[1],
        $text,
    );
    $text = preg_replace('/#(ui[A-Za-z0-9_]+)/', '$1', $text);
    // libui leaves `[Default: ...]` fragments dangling in prose (both unfilled
    // `TODO` placeholders and documented values like `FALSE`); none of them read
    // well inline, so strip the whole bracketed fragment.
    $text = preg_replace('/\s*`?\[Default:?[^\]]*\]`?/i', '', $text);
    // Some descriptions trail off into a literal `TODO:` placeholder clause that
    // dangles off an unfinished sentence (e.g. "...: TODO: clarify ..."). Drop the
    // whole trailing clause back to the last completed sentence boundary.
    $text = preg_replace('/\s*[^.!?]*\bTODO:.*$/i', '', $text);
    // Normalise a recurring grammar typo in libui's notes.
    $text = str_replace('control neither destroyed nor freed', 'control is neither destroyed nor freed', $text);
    // Strip libui's recurring string-ownership boilerplate wherever it appears —
    // multi-line continuation lines append it to otherwise-useful descriptions.
    $text = preg_replace(
        [
            '/\s*A (?:valid,?\s+)?`?NUL`? terminated UTF-8 string\.?/i',
            '/\s*Data is copied internally\.?\s*Ownership is not transferred\.?/i',
            '/\s*Caller is responsible for freeing the data with `?uiFreeText\(\)`?\.?/i',
        ],
        '',
        $text,
    );
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B\\");
    if ($text === '') {
        return '';
    }

    $fluffPatterns = [
        '/^ui[A-Za-z0-9_]+\s+instance\.$/',
        '/^Callback function\.$/',
        '/^User data to be passed to the callback\.$/',
        '/^Back reference to the instance that (triggered|initiated) the callback\.$/',
        '/^User data registered with the sender instance\.$/',
    ];
    if (array_filter($fluffPatterns, fn (string $pattern): bool => preg_match($pattern, $text) === 1) !== []) {
        return '';
    }

    return $text;
}

/**
 * Reorder docblock tags into [params, return, notes/warnings]. Callers append
 * @return after @note for convenience; PHPDoc convention puts @return first.
 *
 * @param list<string> $tags
 * @return list<string>
 */
function orderDocTags(array $tags): array
{
    $params = [];
    $returns = [];
    $rest = [];
    foreach ($tags as $tag) {
        if (str_starts_with($tag, '@param')) {
            $params[] = $tag;
        } elseif (str_starts_with($tag, '@return')) {
            $returns[] = $tag;
        } else {
            $rest[] = $tag;
        }
    }

    return [...$params, ...$returns, ...$rest];
}

/**
 * Render a method/function docblock.
 *
 * @param array<string, array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}> $docs
 * @param list<string> $extraTags Full tag lines without the leading `*`.
 */
function docBlock(string $uiName, array $docs, string $indent, array $extraTags = []): string
{
    $doc = $docs[$uiName] ?? emptyDoc();
    $summary = $doc['summary'];
    if ($summary === '' && $extraTags === []) {
        return "{$indent}/** libui: {$uiName} */\n";
    }

    // Emit tags in the canonical order [params, return, notes/warnings]; callers
    // assemble them notes-last, so reorder here rather than at every call site.
    $orderedTags = orderDocTags($extraTags);

    $lines = ["{$indent}/**"];
    if ($summary !== '') {
        $lines[] = "{$indent} * {$summary}";
    }
    if ($summary !== '' && $orderedTags !== []) {
        $lines[] = "{$indent} *";
    }
    foreach ($orderedTags as $tag) {
        if ($tag === '') {
            continue;
        }
        $lines[] = "{$indent} * {$tag}";
    }
    $lines[] = "{$indent} *";
    // Bare `@see uiFn` resolves to no PHP symbol, so emit a prose label instead.
    $lines[] = "{$indent} * libui: {$uiName}";
    $lines[] = "{$indent} */";

    return implode("\n", $lines) . "\n";
}

// =============================================================================
// 2. Parsing
// =============================================================================

/**
 * The 26 widget types, from ui.h's `#define uiX(this)` cast macros.
 *
 * @return list<string>
 */
function typeList(string $rawHeader): array
{
    preg_match_all('/#define\s+(ui[A-Za-z0-9]+)\(this\)/', $rawHeader, $matches);
    $types = array_values(array_unique($matches[1]));
    usort($types, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    return $types;
}

/**
 * Parse function prototypes out of the cleaned header.
 *
 * @return array<string, ParsedFunction>
 */
function parseFunctions(string $cleaned): array
{
    $flattened = preg_replace('/\{[^{}]*\}/s', '{}', $cleaned);
    $functions = [];
    foreach (explode(';', $flattened) as $statement) {
        $statement = trim(preg_replace('/\s+/', ' ', $statement));
        if ($statement === '' || preg_match('/^(typedef|struct|enum|union)\b/', $statement)) {
            continue;
        }
        if (! preg_match('/^(?<returnType>.*)\b(?<name>ui[A-Za-z0-9_]+)\s*\((?<params>.*)\)$/', $statement, $matches)) {
            continue;
        }
        $returnType = trim($matches['returnType']);
        if ($returnType === '') {
            continue;
        }
        $functions[$matches['name']] = [
            'name' => $matches['name'],
            'ret' => $returnType,
            'params' => parseParameters($matches['params']),
        ];
    }

    return $functions;
}

/**
 * Split a C parameter list on top-level commas; classify each param.
 *
 * @return list<ParsedParam>
 */
function parseParameters(string $text): array
{
    $text = trim($text);
    if ($text === '' || $text === 'void') {
        return [];
    }
    $parts = [];
    $depth = 0;
    $buffer = '';
    foreach (str_split($text) as $character) {
        if ($character === '(') {
            $depth++;
        } elseif ($character === ')') {
            $depth--;
        }
        if ($character === ',' && $depth === 0) {
            $parts[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }
    if (trim($buffer) !== '') {
        $parts[] = trim($buffer);
    }

    $params = [];
    foreach ($parts as $part) {
        if (str_contains($part, '(*')) {
            $params[] = ['raw' => $part, 'isCallback' => true, 'type' => $part, 'name' => 'cb'];
        } elseif (preg_match('/^(?<type>.*?)(?<name>[A-Za-z_]\w*)$/', $part, $matches) && trim($matches['type']) !== '') {
            $params[] = ['raw' => $part, 'isCallback' => false, 'type' => trim($matches['type']), 'name' => $matches['name']];
        } else {
            $params[] = ['raw' => $part, 'isCallback' => false, 'type' => $part, 'name' => 'arg'];
        }
    }

    return $params;
}

/**
 * Parse the expanded enums: `typedef unsigned int NAME; enum { ... };`.
 *
 * @return array<string, array<string, int>>
 */
function parseEnums(string $cleaned): array
{
    preg_match_all(
        '/typedef unsigned int (ui[A-Za-z0-9]+);\s*enum\s*\{(.*?)\}/s',
        $cleaned,
        $rawMatches,
        PREG_SET_ORDER,
    );
    $enums = [];
    foreach ($rawMatches as $matchSet) {
        $enumName = $matchSet[1];
        $members = [];
        $counter = 0;
        $entries = array_filter(
            array_map('trim', explode(',', $matchSet[2])),
            fn (string $entry): bool => $entry !== '',
        );
        foreach ($entries as $entry) {
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $entry, $match)) {
                $value = evaluateEnumExpression(trim($match[2]), $members);
                $members[$match[1]] = $value;
                $counter = $value + 1;
            } else {
                $members[$entry] = $counter++;
            }
        }
        $enums[$enumName] = $members;
    }

    return $enums;
}

/** @param array<string, int> $existingMembers */
function evaluateEnumExpression(string $expression, array $existingMembers): int
{
    $expression = trim($expression);
    if (preg_match('/^0x[0-9a-fA-F]+$/', $expression)) {
        return (int) hexdec($expression);
    }
    if (preg_match('/^-?\d+$/', $expression)) {
        return (int) $expression;
    }
    if (preg_match('/^(\w+)\s*<<\s*(\w+)$/', $expression, $matches)) {
        return (int) $matches[1] << (int) $matches[2];
    }
    if (isset($existingMembers[$expression])) {
        return $existingMembers[$expression];
    }

    return 0;
}

// =============================================================================
// 3. Type classification & marshalling
// =============================================================================

/** Strip the `ui` prefix from a libui type identifier (uiButton → Button). */
function stripUiPrefix(string $typeIdentifier): string
{
    return substr($typeIdentifier, 2);
}

/**
 * @param array<string, array<string, int>> $enums
 * @param list<string> $types
 * @return Classification
 */
function classify(string $type, array $enums, array $types): array
{
    $text = trim($type);
    $isConst = str_contains($text, 'const');
    $text = trim(str_replace('const', '', $text));
    $stars = substr_count($text, '*');
    $base = trim(preg_replace(['/\*/', '/\bstruct\b/', '/\s+/'], ['', '', ' '], $text));

    if ($stars === 0) {
        if ($base === 'void') {
            return ['kind' => 'void'];
        }
        if ($base === 'double') {
            return ['kind' => 'double'];
        }
        // A scalar C `char` binds to a one-character PHP string under FFI, not an
        // int (this is what bit OpenTypeFeaturesAdd/Get).
        if ($base === 'char') {
            return ['kind' => 'char'];
        }
        if (isset($enums[$base])) {
            return ['kind' => 'enum', 'name' => $base];
        }

        return ['kind' => 'int'];
    }
    if ($base === 'char') {
        return ['kind' => $isConst ? 'string_borrow' : 'string_owned'];
    }
    if ($base === 'uiControl') {
        return ['kind' => 'control'];
    }
    if (in_array($base, $types, true)) {
        return ['kind' => 'widget', 'name' => $base];
    }
    if ($base === 'double' || $base === 'int' || str_contains($base, 'int') || in_array($base, ['size_t', 'uintptr_t'], true)) {
        return ['kind' => 'cdata', 'scalarOut' => true];
    }

    return ['kind' => 'cdata'];
}

/** @param Classification $classification */
function phpTypeForParam(array $classification): string
{
    return match ($classification['kind']) {
        'double' => 'float',
        'char', 'string_borrow', 'string_owned' => 'string',
        'enum' => '\\Libui\\Generated\\Enum\\' . enumClassName($classification['name']),
        'control', 'widget' => '\\Libui\\Control',
        'cdata' => '\\FFI\\CData',
        default => 'int',
    };
}

/** @param Classification $classification */
function marshalArg(array $classification, string $variable): string
{
    if (($classification['scalarOut'] ?? false) === true) {
        return "\\FFI::addr({$variable})";
    }

    return match ($classification['kind']) {
        'enum' => "{$variable}->value",
        'control' => "\\Libui\\Ffi::control({$variable}->handle())",
        'widget' => "{$variable}->handle()",
        default => $variable,
    };
}

/** PHP class name for a libui enum, avoiding reserved words (uiForEach -> UiForEach). */
function enumClassName(string $ui): string
{
    $s = stripUiPrefix($ui);

    return isReservedWord($s) ? 'Ui' . $s : $s;
}

/**
 * @param Classification $classification
 * @param list<string> $generatedTypes
 */
function phpTypeForReturn(array $classification, array $generatedTypes): string
{
    return match ($classification['kind']) {
        'void' => 'void',
        'double' => 'float',
        'char', 'string_borrow', 'string_owned' => 'string',
        'enum' => '\\Libui\\Generated\\Enum\\' . enumClassName($classification['name']),
        'widget' => in_array($classification['name'], $generatedTypes, true)
            ? '\\Libui\\Generated\\' . stripUiPrefix($classification['name'])
            : '\\FFI\\CData',
        'control', 'cdata' => '\\FFI\\CData',
        default => 'int',
    };
}

/**
 * PHP parameter type for the raw \FFI boundary (not the high-level wrapper).
 *
 * @param array{raw: string, isCallback: bool, type: string, name: string} $param
 * @param array<string, array<string, int>> $enums
 */
function ffiTypeForParam(array $param, array $enums): string
{
    if ($param['isCallback']) {
        return 'callable';
    }

    $classification = classify($param['type'], $enums, []);

    return match ($classification['kind']) {
        'void' => 'void',
        'double' => 'float',
        'enum', 'int' => 'int',
        'char' => 'string',
        'string_borrow', 'string_owned' => 'string|\\FFI\\CData',
        default => '?\\FFI\\CData',
    };
}

/**
 * PHP return type for the raw \FFI boundary (not the high-level wrapper).
 *
 * @param array{kind: string, name?: string, scalarOut?: bool} $classification
 */
function ffiTypeForReturn(array $classification): string
{
    return match ($classification['kind']) {
        'void' => 'void',
        'double' => 'float',
        'enum', 'int' => 'int',
        'string_borrow', 'string_owned' => '?\\FFI\\CData',
        default => '?\\FFI\\CData',
    };
}

/**
 * @param Classification $classification
 * @param list<string> $generatedTypes
 */
function returnStatement(
    array $classification,
    string $call,
    array $generatedTypes,
    bool $asBoolean,
): string {
    if ($classification['kind'] === 'void') {
        return "        {$call};";
    }
    if ($asBoolean && $classification['kind'] === 'int') {
        return "        return {$call} !== 0;";
    }

    return match ($classification['kind']) {
        'string_owned' => "        return \\Libui\\Ffi::ownedString({$call});",
        'string_borrow' => "        return \\Libui\\Ffi::borrowedString({$call});",
        'enum' => "        return \\Libui\\Generated\\Enum\\" . enumClassName($classification['name']) . "::from({$call});",
        'widget' => in_array($classification['name'], $generatedTypes, true)
            ? "        return \\Libui\\Generated\\" . stripUiPrefix($classification['name']) . "::wrap({$call});"
            : "        return {$call};",
        default => "        return {$call};",
    };
}

// =============================================================================
// 4. Emission
// =============================================================================

function phpDocType(string $type): string
{
    return $type;
}

/**
 * @param array<string, array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}> $docs
 * @param array{raw: string, isCallback: bool, type: string, name: string} $param
 */
function methodParamDoc(
    string $uiName,
    array $docs,
    array $param,
    string $phpType,
    string $variable,
    bool $isOutParameter = false,
): string {
    $doc = $docs[$uiName] ?? emptyDoc();
    $description = $doc['params'][$param['name']] ?? '';
    if ($isOutParameter) {
        $description = trim(($description === '' ? '' : "{$description} ") . 'Output pointer written by libui.');
    }
    if ($description === '') {
        return '';
    }

    return '@param ' . phpDocType($phpType) . " {$variable} {$description}";
}

/** @param array<string, array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}> $docs */
function methodReturnDoc(string $uiName, array $docs, string $phpType): string
{
    $doc = $docs[$uiName] ?? emptyDoc();
    $description = $doc['return'];
    if ($description === '' || preg_match('/^A new ui[A-Za-z0-9_]+ instance\.$/', $description)) {
        return '';
    }

    return '@return ' . phpDocType($phpType) . " {$description}";
}

/**
 * @param array<string, array{summary: string, params: array<string, string>, return: string, notes: list<string>, warnings: list<string>}> $docs
 * @return list<string>
 */
function noteDocTags(string $uiName, array $docs): array
{
    $doc = $docs[$uiName] ?? emptyDoc();
    $tags = [];
    foreach ($doc['warnings'] as $warning) {
        $tags[] = "@warning {$warning}";
    }
    foreach ($doc['notes'] as $note) {
        $tags[] = "@note {$note}";
    }

    return $tags;
}

/** @return list<string> */
function callbackDocTags(?string $deviation): array
{
    if ($deviation === 'int') {
        return ['@param callable(static): (bool|int) $cb Return false/0 to cancel, true/non-zero to continue.'];
    }
    if ($deviation === 'menuitem') {
        return ['@param callable(static, \\FFI\\CData): void $cb Receives this menu item and the source uiWindow handle.'];
    }

    return ['@param callable(static): void $cb Receives this widget.'];
}

function eventHandlerBody(string $methodName, ?string $deviation): string
{
    $parameters = match ($deviation) {
        'int' => '$sender, $data',
        'menuitem' => '$sender, $window, $data',
        default => '$sender, $data',
    };

    $payload = match ($deviation) {
        'int' => "            try {\n"
            . "                \$result = \$cb(\$this);\n"
            . "                return \$result === false ? 0 : (\\is_int(\$result) ? \$result : 1);\n"
            . "            } catch (\\Throwable \$exception) {\n"
            . "                \\fwrite(\\STDERR, \"[{$methodName}] {\$exception->getMessage()}\\n\");\n"
            . "                return 0;\n"
            . '            }',
        'menuitem' => "            try {\n"
            . "                \$cb(\$this, \$window);\n"
            . "            } catch (\\Throwable \$exception) {\n"
            . "                \\fwrite(\\STDERR, \"[{$methodName}] {\$exception->getMessage()}\\n\");\n"
            . '            }',
        default => "            try {\n"
            . "                \$cb(\$this);\n"
            . "            } catch (\\Throwable \$exception) {\n"
            . "                \\fwrite(\\STDERR, \"[{$methodName}] {\$exception->getMessage()}\\n\");\n"
            . '            }',
    };

    return "        \$fn = static::keep(function ({$parameters}) use (\$cb) {\n{$payload}\n        });";
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $entryPath = "{$directory}/{$entry}";
        is_dir($entryPath) ? removeDirectory($entryPath) : unlink($entryPath);
    }
    rmdir($directory);
}

function writeFile(string $path, string $content): void
{
    @mkdir(dirname($path), 0o777, true);
    file_put_contents($path, $content);
}

/**
 * Build one widget method (getter/setter/action/event) or return null to skip.
 *
 * @param ParsedFunction $function
 * @param GeneratorContext $context
 */
function emitMethod(array $function, string $type, array $context): ?string
{
    $annotations = $context['annotations'];
    $enums = $context['enums'];
    $types = $context['types'];
    $generatedTypes = $context['generatedTypes'];
    $docs = $context['docs'];

    $name = $function['name'];
    $methodSuffix = substr($name, strlen($type));
    if ($methodSuffix === '') {
        return null;
    }
    $method = lcfirst($methodSuffix);
    $params = $function['params'];
    array_shift($params);
    $isBool = in_array($name, $annotations['bool_funcs'], true);

    // --- event handlers ------------------------------------------------------
    $hasCallback = (bool) array_filter($params, fn (array $param): bool => $param['isCallback']);
    if (str_starts_with($methodSuffix, 'On') && $hasCallback) {
        $deviation = $annotations['deviating_callbacks'][$name] ?? null;
        $body = eventHandlerBody($method, $deviation);
        $replaceNote = '@note Registering a second handler supersedes the first at the C level; ' . 'the prior trampoline stays retained for the lifetime of this object.';
        $docTags = array_merge(callbackDocTags($deviation), noteDocTags($name, $docs), [$replaceNote]);

        return (
            docBlock($name, $docs, '    ', $docTags)
            . "    public function {$method}(callable \$cb): static\n    {\n"
            . "{$body}\n"
            . "        \\Libui\\Ffi::get()->{$name}(\$this->handle, \$fn, null);\n"
            . "        return \$this;\n    }\n"
        );
    }

    // --- ordinary methods ----------------------------------------------------
    $signature = [];
    $args = ['$this->handle'];
    $docTags = [];
    foreach ($params as $index => $param) {
        $classification = classify($param['type'], $enums, $types);
        $phpType =
            $isBool && $classification['kind'] === 'int' && str_starts_with($methodSuffix, 'Set') && $index === (count($params) - 1)
                ? 'bool'
                : phpTypeForParam($classification);
        $variable = '$' . ($param['name'] ?: "a{$index}");
        $signature[] = "{$phpType} {$variable}";
        $args[] = $phpType === 'bool'
            ? "(int) {$variable}"
            : marshalArg($classification, $variable);
        $docTags[] = methodParamDoc(
            $name,
            $docs,
            $param,
            $phpType,
            $variable,
            ($classification['scalarOut'] ?? false) === true,
        );
    }
    $call = "\\Libui\\Ffi::get()->{$name}(" . implode(', ', $args) . ')';
    $returnClassification = classify($function['ret'], $enums, $types);
    $docTags = array_values(array_filter(array_merge($docTags, noteDocTags($name, $docs))));

    $isSetter = str_starts_with($methodSuffix, 'Set');
    if ($isSetter || $returnClassification['kind'] === 'void') {
        return (
            docBlock($name, $docs, '    ', $docTags)
            . "    public function {$method}("
            . implode(', ', $signature)
            . "): static\n    {\n"
            . "        {$call};\n        return \$this;\n    }\n"
        );
    }

    $phpReturnType = $isBool && $returnClassification['kind'] === 'int'
        ? 'bool'
        : phpTypeForReturn($returnClassification, $generatedTypes);
    $returnDoc = methodReturnDoc($name, $docs, $phpReturnType);
    if ($returnDoc !== '') {
        $docTags[] = $returnDoc;
    }

    return (
        docBlock($name, $docs, '    ', $docTags)
        . "    public function {$method}("
        . implode(', ', $signature)
        . "): {$phpReturnType}\n    {\n"
        . returnStatement(
            $returnClassification,
            $call,
            $generatedTypes,
            $isBool && $returnClassification['kind'] === 'int',
        )
        . "\n    }\n"
    );
}

/**
 * Emit one widget class.
 *
 * @param list<ParsedFunction> $members
 * @param ConstructorConfig $constructorConfig
 * @param GeneratorContext $context
 */
function emitWidget(string $type, array $members, array $constructorConfig, array $context): string
{
    $enums = $context['enums'];
    $types = $context['types'];
    $functions = $context['functions'];
    $docs = $context['docs'];

    $class = stripUiPrefix($type);
    $methods = [];

    // constructor (primary) + factories
    $primaryFn = $constructorConfig['primary'] ?? null;
    if ($primaryFn && isset($functions[$primaryFn])) {
        $params = $functions[$primaryFn]['params'];
        $signature = [];
        $args = [];
        $docTags = [];
        foreach ($params as $index => $param) {
            $classification = classify($param['type'], $enums, $types);
            $hasParam = str_starts_with($param['name'], 'has') && $classification['kind'] === 'int';
            $phpType = $hasParam ? 'bool' : phpTypeForParam($classification);
            $variable = '$' . ($param['name'] ?: "a{$index}");
            $signature[] = "{$phpType} {$variable}";
            $args[] = $hasParam ? "(int) {$variable}" : marshalArg($classification, $variable);
            $docTags[] = methodParamDoc($primaryFn, $docs, $param, $phpType, $variable);
        }
        $docTags = array_values(array_filter(array_merge($docTags, noteDocTags($primaryFn, $docs))));
        $methods[] =
            docBlock($primaryFn, $docs, '    ', $docTags)
            . '    public function __construct('
            . implode(', ', $signature)
            . ")\n    {\n"
            . "        \$this->handle = \\Libui\\Ffi::get()->{$primaryFn}("
            . implode(', ', $args)
            . ");\n    }\n";
    }
    foreach ($constructorConfig['factories'] ?? [] as $factoryMethod => $fnName) {
        if (! isset($functions[$fnName])) {
            continue;
        }
        $params = $functions[$fnName]['params'];
        $signature = [];
        $args = [];
        $docTags = [];
        foreach ($params as $index => $param) {
            $classification = classify($param['type'], $enums, $types);
            $variable = '$' . ($param['name'] ?: "a{$index}");
            $phpType = phpTypeForParam($classification);
            $signature[] = "{$phpType} {$variable}";
            $args[] = marshalArg($classification, $variable);
            $docTags[] = methodParamDoc($fnName, $docs, $param, $phpType, $variable);
        }
        $docTags = array_values(array_filter(array_merge($docTags, noteDocTags($fnName, $docs))));
        $methods[] =
            docBlock($fnName, $docs, '    ', $docTags)
            . "    public static function {$factoryMethod}("
            . implode(', ', $signature)
            . "): static\n    {\n"
            . "        return static::wrap(\\Libui\\Ffi::get()->{$fnName}("
            . implode(', ', $args)
            . "));\n    }\n";
    }

    // member methods (skip names already taken by the constructor or factories)
    $seen = ['__construct' => true] + array_fill_keys(array_keys($constructorConfig['factories'] ?? []), true);
    foreach ($members as $memberFunction) {
        $methodCode = emitMethod($memberFunction, $type, $context);
        if ($methodCode === null) {
            continue;
        }
        if (preg_match('/function (\w+)\(/', $methodCode, $match)) {
            if (isset($seen[$match[1]])) {
                continue;
            }
            $seen[$match[1]] = true;
        }
        $methods[] = $methodCode;
    }

    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated;\n\n"
        . "use Libui\\Control;\n\n"
        . "/**\n * GENERATED wrapper for libui `{$type}`. DO NOT EDIT — run `composer regen`.\n"
        . " * Add convenience methods in a hand-written Libui\\\\{$class} subclass instead.\n *\n"
        . " * @generated from libui-ng ui.h by tools/generate.php\n"
        . " */\n"
        . "class {$class} extends Control\n{\n"
        . implode("\n", $methods)
        . "}\n"
    );
}

/**
 * Emit a PHP backed enum.
 *
 * @param array<string, int> $members
 * @param array<string, DocData> $docs
 */
function emitEnum(string $name, array $members, array $docs = []): string
{
    $class = enumClassName($name);
    $prefix = longestCommonPrefix(array_keys($members));
    $cases = [];
    $usedNames = [];
    foreach ($members as $member => $value) {
        $case = substr($member, strlen($prefix));
        if ($case === '' || ctype_digit($case[0]) || isReservedWord($case) || isset($usedNames[strtolower($case)])) {
            $case = stripUiPrefix($member);
        }
        $usedNames[strtolower($case)] = true;
        $cases[] = "    case {$case} = {$value};";
    }

    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated\\Enum;\n\n" . enumDocBlock($name, $docs) . "enum {$class}: int\n{\n" . implode("\n", $cases) . "\n}\n";
}

/**
 * Render the class docblock for an enum, folding in any harvested summary.
 *
 * @param array<string, DocData> $docs
 */
function enumDocBlock(string $name, array $docs): string
{
    $doc = $docs[$name] ?? emptyDoc();
    $summary = $doc['summary'];
    if ($summary === '') {
        return "/**\n * GENERATED from libui `{$name}`. DO NOT EDIT.\n *\n * @generated from libui-ng ui.h by tools/generate.php\n */\n";
    }

    return "/**\n * {$summary}\n *\n * GENERATED from libui `{$name}`. DO NOT EDIT.\n *\n * @generated from libui-ng ui.h by tools/generate.php\n */\n";
}

/**
 * Emit a bit-flags enum as a const class.
 *
 * @param array<string, int> $members
 */
function emitFlags(string $name, array $members): string
{
    $class = stripUiPrefix($name);
    $prefix = longestCommonPrefix(array_keys($members));
    $constants = [];
    foreach ($members as $member => $value) {
        $constant = substr($member, strlen($prefix)) ?: stripUiPrefix($member);
        $constants[] = "    public const int {$constant} = {$value};";
    }

    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated\\Flags;\n\n"
        . "/**\n * GENERATED bit-flags from libui `{$name}`. DO NOT EDIT.\n *\n"
        . " * @generated from libui-ng ui.h by tools/generate.php\n"
        . " */\n"
        . "final class {$class}\n{\n"
        . implode("\n", $constants)
        . "\n\n"
        . "    public static function has(int \$mask, int \$flag): bool\n    {\n"
        . "        return (\$mask & \$flag) === \$flag;\n    }\n}\n"
    );
}

/**
 * Emit the static facade of free (non-widget) functions.
 *
 * @param array<string, ParsedFunction> $functions
 * @param GeneratorContext $context
 */
function emitFacade(array $functions, array $context): string
{
    $enums = $context['enums'];
    $types = $context['types'];
    $generatedTypes = $context['generatedTypes'];
    $docs = $context['docs'];

    $methods = [];
    foreach ($functions as $function) {
        $method = lcfirst(stripUiPrefix($function['name']));
        $signature = [];
        $args = [];
        $docTags = [];
        foreach ($function['params'] as $index => $param) {
            $classification = classify($param['type'], $enums, $types);
            $variable = '$' . ($param['name'] ?: "a{$index}");
            $phpType = phpTypeForParam($classification);
            $signature[] = "{$phpType} {$variable}";
            $args[] = marshalArg($classification, $variable);
            $docTags[] = methodParamDoc(
                $function['name'],
                $docs,
                $param,
                $phpType,
                $variable,
                ($classification['scalarOut'] ?? false) === true,
            );
        }
        $call = "\\Libui\\Ffi::get()->{$function['name']}(" . implode(', ', $args) . ')';
        $returnClassification = classify($function['ret'], $enums, $types);
        $phpReturnType = phpTypeForReturn($returnClassification, $generatedTypes);
        $body = returnStatement($returnClassification, $call, $generatedTypes, false);
        $docTags = array_values(array_filter(array_merge($docTags, noteDocTags($function['name'], $docs))));
        $returnDoc = methodReturnDoc($function['name'], $docs, $phpReturnType);
        if ($returnDoc !== '') {
            $docTags[] = $returnDoc;
        }
        $methods[] =
            docBlock($function['name'], $docs, '    ', $docTags)
            . "    public static function {$method}("
            . implode(', ', $signature)
            . "): {$phpReturnType}\n    {\n"
            . "{$body}\n    }\n";
    }

    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated;\n\n"
        . "/**\n * GENERATED facade for libui free functions (dialogs, etc.). DO NOT EDIT.\n *\n"
        . " * @generated from libui-ng ui.h by tools/generate.php\n"
        . " */\n"
        . "final class Ui\n{\n"
        . implode("\n", $methods)
        . "}\n"
    );
}

/**
 * Build the @method lines shared by the FfiFunctions interface and the PHPStan stub.
 *
 * @param array<string, array{name: string, ret: string, params: list<array{raw: string, isCallback: bool, type: string, name: string}>}> $functions
 * @param array<string, array<string, int>> $enums
 * @return list<string>
 */
function ffiMethodLines(array $functions, array $enums): array
{
    ksort($functions);
    $methods = [];

    foreach ($functions as $function) {
        $signature = [];
        foreach ($function['params'] as $index => $param) {
            $variable = '$' . ($param['name'] !== '' ? $param['name'] : "a{$index}");
            $signature[] = ffiTypeForParam($param, $enums) . ' ' . $variable;
        }
        $returnClassification = classify($function['ret'], $enums, []);
        $returnType = ffiTypeForReturn($returnClassification);
        $params = '(' . implode(', ', $signature) . ')';
        $methods[] = " * @method {$returnType} {$function['name']}{$params}";
    }

    return $methods;
}

/**
 * Emit a docblock-only interface describing every libui function bound by \FFI::cdef().
 *
 * @param array<string, array{name: string, ret: string, params: list<array{raw: string, isCallback: bool, type: string, name: string}>}> $functions
 * @param array<string, array<string, int>> $enums
 */
function emitFfiFunctionsInterface(array $functions, array $enums): string
{
    $methods = ffiMethodLines($functions, $enums);

    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated;\n\n"
        . "/**\n * GENERATED docblock contract for libui functions bound via FFI::cdef(). DO NOT EDIT.\n *\n"
        . " * @generated from libui-ng ui.h by tools/generate.php\n"
        . ($methods === [] ? '' : " *\n" . implode("\n", $methods) . "\n")
        . " */\n"
        . "interface FfiFunctions\n{\n}\n"
    );
}

/**
 * Emit a PHPStan stub that teaches static analysis about the dynamic libui methods on \FFI.
 *
 * @param array<string, array{name: string, ret: string, params: list<array{raw: string, isCallback: bool, type: string, name: string}>}> $functions
 * @param array<string, array<string, int>> $enums
 */
function emitFfiStub(array $functions, array $enums): string
{
    $methods = ffiMethodLines($functions, $enums);

    return (
        "<?php\n\ndeclare(strict_types=1);\n\n"
        . "/**\n * GENERATED PHPStan stub for the built-in \\FFI class. DO NOT EDIT.\n *\n"
        . " * libui-ng functions are bound dynamically on the singleton FFI handle; this stub\n"
        . " * gives PHPStan a static view of those methods so calls like \\FFI::uiMain() are\n"
        . " * understood without baselining every site.\n *\n"
        . " * @generated from libui-ng ui.h by tools/generate.php\n"
        . ($methods === [] ? '' : " *\n" . implode("\n", $methods) . "\n")
        . " */\n"
        . "class FFI\n{\n}\n"
    );
}

// --- small helpers -----------------------------------------------------------

/** @param list<string>|array<int, string> $strings */
function longestCommonPrefix(array $strings): string
{
    if ($strings === []) {
        return '';
    }
    $prefix = $strings[0];
    foreach ($strings as $string) {
        while ($prefix !== '' && ! str_starts_with($string, $prefix)) {
            $prefix = substr($prefix, 0, -1);
        }
    }

    return $prefix;
}

function isReservedWord(string $word): bool
{
    static $reservedWords = [
        'continue',
        'default',
        'list',
        'class',
        'function',
        'for',
        'do',
        'else',
        'new',
        'print',
        'exit',
        'echo',
        'and',
        'or',
        'xor',
        'match',
        'fn',
        'enum',
        'try',
        'catch',
        'goto',
        'global',
        'static',
        'abstract',
        'final',
        'const',
        'case',
        'switch',
        'break',
        'return',
        'throw',
        'yield',
        'clone',
        'trait',
        'interface',
        'namespace',
        'use',
        'as',
        'if',
        'while',
        'foreach',
        'endif',
        'array',
        'unset',
        'isset',
        'empty',
    ];

    return in_array(strtolower($word), $reservedWords, true);
}

// =============================================================================
// 5. Main pipeline
// =============================================================================

function readInput(): string
{
    if (! is_file(UI_H)) {
        fwrite(STDERR, 'ui.h not found at ' . UI_H . " (run composer build-lib first)\n");
        exit(1);
    }

    return file_get_contents(UI_H);
}

/**
 * @param list<string> $generatedTypes
 * @param GeneratorAnnotations $annotations
 * @return array{array<string, ConstructorConfig>, array<string, true>}
 */
function buildConstructorMaps(array $generatedTypes, array $annotations): array
{
    $constructorConfig = [];
    $constructorMap = [];

    foreach ($generatedTypes as $typeName) {
        $config = $annotations['constructors'][$typeName] ?? [
            'primary' => 'uiNew' . stripUiPrefix($typeName),
            'factories' => [],
        ];
        $constructorConfig[$typeName] = $config;
        if ($config['primary']) {
            $constructorMap[$config['primary']] = true;
        }
        foreach ($config['factories'] ?? [] as $factoryMethod) {
            $constructorMap[$factoryMethod] = true;
        }
    }

    return [$constructorConfig, $constructorMap];
}

/**
 * @param array<string, ParsedFunction> $functions
 * @param list<string> $allTypes
 * @param array<string, true> $constructorMap
 * @param list<string> $excludedTypes
 * @return array{array<string, list<ParsedFunction>>, array<string, ParsedFunction>}
 */
function partitionFunctions(
    array $functions,
    array $allTypes,
    array $constructorMap,
    array $excludedTypes,
): array {
    $functionsByType = [];
    foreach ($allTypes as $typeName) {
        if (! in_array($typeName, $excludedTypes, true)) {
            $functionsByType[$typeName] = [];
        }
    }
    $freeFunctions = [];

    foreach ($functions as $name => $function) {
        if (isset($constructorMap[$name])) {
            continue;
        }
        $owner = null;
        foreach ($allTypes as $typeName) {
            $matchesType = str_starts_with($name, $typeName) && strlen($name) > strlen($typeName) && ctype_upper($name[strlen($typeName)]);
            if ($matchesType) {
                $owner = $typeName;
                break;
            }
        }
        if ($owner === null) {
            $freeFunctions[$name] = $function;
            continue;
        }
        if (in_array($owner, $excludedTypes, true)) {
            continue;
        }
        $functionsByType[$owner][] = $function;
    }

    return [$functionsByType, $freeFunctions];
}

/**
 * @param array<string, ParsedFunction> $functions
 * @param array<string, array<string, int>> $enums
 */
function emitFfiContracts(array $functions, array $enums): void
{
    writeFile(GEN_DIR . '/FfiFunctions.php', emitFfiFunctionsInterface($functions, $enums));
    writeFile(ROOT . '/stubs/FFI.php', emitFfiStub($functions, $enums));
}

/**
 * @param list<string> $generatedTypes
 * @param array<string, list<ParsedFunction>> $functionsByType
 * @param array<string, ConstructorConfig> $constructorConfig
 * @param GeneratorContext $context
 * @return array{int, int, int} [widgetCount, methodCount, stubCount]
 */
function emitAllWidgets(
    array $generatedTypes,
    array $functionsByType,
    array $constructorConfig,
    array $context,
): array {
    $widgetCount = 0;
    $methodCount = 0;
    $stubCount = 0;

    foreach ($generatedTypes as $typeName) {
        $class = stripUiPrefix($typeName);
        $classSource = emitWidget(
            $typeName,
            $functionsByType[$typeName],
            $constructorConfig[$typeName],
            $context,
        );
        writeFile(GEN_DIR . '/' . $class . '.php', $classSource);
        $widgetCount++;
        $methodCount += substr_count($classSource, '    public function ') + substr_count($classSource, '    public static function ');

        $stubPath = ROOT . '/src/' . $class . '.php';
        if (! file_exists($stubPath)) {
            writeFile(
                $stubPath,
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui;\n\n"
                . "/**\n * {$class} widget. Hand-editable — add convenience methods here.\n"
                . " * Inherits the generated API from Generated\\\\{$class}.\n */\n"
                . "class {$class} extends Generated\\{$class}\n{\n}\n",
            );
            $stubCount++;
        }
    }

    return [$widgetCount, $methodCount, $stubCount];
}

/**
 * @param array<string, array<string, int>> $enums
 * @param GeneratorAnnotations $annotations
 * @param array<string, DocData> $docs
 */
function emitAllEnums(array $enums, array $annotations, array $docs): int
{
    $enumCount = 0;
    foreach ($enums as $name => $members) {
        if (in_array($name, $annotations['flag_enums'], true)) {
            writeFile(GEN_DIR . '/Flags/' . stripUiPrefix($name) . '.php', emitFlags($name, $members));
        } else {
            writeFile(GEN_DIR . '/Enum/' . enumClassName($name) . '.php', emitEnum($name, $members, $docs));
        }
        $enumCount++;
    }

    return $enumCount;
}

/**
 * @param array<string, ParsedFunction> $functions
 * @param array<string, array<string, int>> $enums
 * @param array<string, DocData> $docs
 * @param array<string, ParsedFunction> $facadeFunctions
 * @param GeneratorAnnotations $annotations
 */
function printReport(
    array $functions,
    array $enums,
    array $docs,
    int $widgetCount,
    int $methodCount,
    int $stubCount,
    int $enumCount,
    array $facadeFunctions,
    array $annotations,
): void {
    $emittedSymbols = array_values(array_unique([
        ...array_keys($functions),
        ...array_keys($enums),
    ]));
    $withSummary = count(array_intersect($emittedSymbols, array_keys($docs)));
    $fellBack = count($emittedSymbols) - $withSummary;

    printf(
        "generated:\n  header   %s\n  widgets  %d classes, ~%d methods (+%d new sugar stubs)\n  enums    %d (+%d flags)\n  facade   %d functions\n  docs     %d/%d symbols got a summary (%d fell back to bare @see)\n",
        basename(GEN_H),
        $widgetCount,
        $methodCount,
        $stubCount,
        $enumCount - count($annotations['flag_enums']),
        count($annotations['flag_enums']),
        count($facadeFunctions),
        $withSummary,
        count($emittedSymbols),
        $fellBack,
    );
}

/** @param GeneratorAnnotations $annotations */
function main(array $annotations): void
{
    $rawHeader = readInput();
    $cleaned = $rawHeader |> cleanHeader(...);
    writeFile(GEN_H, $cleaned);

    $parsedTypes = typeList($rawHeader);
    $parsedFunctions = parseFunctions($cleaned);
    $parsedEnums = parseEnums($cleaned);
    $parsedDocs = harvestDocs($rawHeader);
    $parsedDocs = applyDocOverrides($parsedDocs, $annotations['doc_overrides']);

    $excludedTypes = $annotations['skip_types'];
    $generatedTypes = $parsedTypes |> (fn (array $types): array => array_diff($types, $excludedTypes)) |> array_values(...);

    [$constructorConfig, $constructorMap] = buildConstructorMaps($generatedTypes, $annotations);
    [$functionsByType, $freeFunctions] = partitionFunctions(
        $parsedFunctions,
        $parsedTypes,
        $constructorMap,
        $excludedTypes,
    );

    $context = [
        'enums' => $parsedEnums,
        'types' => $parsedTypes,
        'generatedTypes' => $generatedTypes,
        'functions' => $parsedFunctions,
        'annotations' => $annotations,
        'docs' => $parsedDocs,
    ];

    removeDirectory(GEN_DIR);
    emitFfiContracts($parsedFunctions, $parsedEnums);

    [$widgetCount, $methodCount, $stubCount] = emitAllWidgets(
        $generatedTypes,
        $functionsByType,
        $constructorConfig,
        $context,
    );

    $enumCount = emitAllEnums($parsedEnums, $annotations, $parsedDocs);

    $facadeFunctions = $freeFunctions
        |> (fn (array $functions): array => array_intersect_key(
            $functions,
            array_flip($annotations['facade_funcs']),
        ));
    writeFile(GEN_DIR . '/Ui.php', emitFacade($facadeFunctions, $context));

    printReport(
        $parsedFunctions,
        $parsedEnums,
        $parsedDocs,
        $widgetCount,
        $methodCount,
        $stubCount,
        $enumCount,
        $facadeFunctions,
        $annotations,
    );
}

$annotations = require __DIR__ . '/annotations.php';
main($annotations);

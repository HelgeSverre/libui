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
 */

const ROOT = __DIR__ . '/..';

const UI_H = ROOT . '/third_party/libui-ng/ui.h';

const GEN_H = ROOT . '/src/Native/libui.gen.h';

const GEN_DIR = ROOT . '/src/Generated';

$ANN = require __DIR__ . '/annotations.php';

// =============================================================================
// 1. Header cleaning  (Gate 0)
// =============================================================================

/** Transform libui-ng's ui.h into a header PHP's FFI::cdef() can parse. */
function cleanHeader(string $src): string
{
    // Expand the enum macro exactly as ui.h defines it.
    $src = preg_replace('/_UI_ENUM\(\s*(\w+)\s*\)/', 'typedef unsigned int $1; enum', $src);
    // Strip the visibility token from every declaration.
    $src = preg_replace('/\b_UI_EXTERN\b[ \t]*/', '', $src);
    // Remove comments (block first, then line).
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    $src = preg_replace('#//[^\n]*#', '', $src);
    // Give the forward-declared `struct tm` a concrete (pointer-safe) layout.
    $src = preg_replace(
        '/^[ \t]*struct[ \t]+tm[ \t]*;[ \t]*$/m',
        'struct tm { int tm_sec, tm_min, tm_hour, tm_mday, tm_mon, tm_year, tm_wday, tm_yday, tm_isdst; };',
        $src,
    );
    // Drop preprocessor lines, the C++ `extern "C"` guard and its lone `}`.
    $out = [];
    foreach (explode("\n", $src) as $line) {
        $t = trim($line);
        if ($t !== '' && $t[0] === '#')
            continue;
        if (preg_match('/^extern\s+"C"\s*\{?$/', $t))
            continue;
        if ($t === '}')
            continue;
        $out[] = $line;
    }
    $src = preg_replace("/\n{3,}/", "\n\n", trim(implode("\n", $out)));

    return "// GENERATED from libui-ng ui.h by tools/generate.php — DO NOT EDIT.\n// Re-run `composer regen` to regenerate.\n\n" . $src . "\n";
}

// =============================================================================
// 1b. Doc harvesting  (human-readable summaries from RAW ui.h)
// =============================================================================

/**
 * Harvest a one-line, human-readable summary for every documented
 * `_UI_EXTERN ... uiName(` function and `_UI_ENUM(uiName)` enum, taken from the
 * comment block IMMEDIATELY preceding the declaration in the RAW ui.h.
 *
 * Two comment styles are supported (libui mixes both):
 *   - doxygen `/** ... *\/` blocks   -> first prose line, stop at the first @tag
 *   - older `// ...` line comments   -> joined leading lines, first sentence
 *
 * Returns a map [uiName => summary]. Names with no usable summary are omitted
 * (callers then fall back to the bare `@see`). All summaries are single-line,
 * whitespace-collapsed, length-capped, and stripped of any `*\/` sequence so
 * they can never close the emitted docblock early.
 *
 * Harvested from the RAW header on purpose: cleanHeader() deletes all comments.
 */
function harvestDocs(string $uiHRaw): array
{
    $lines = explode("\n", $uiHRaw);
    $docs = [];

    foreach ($lines as $i => $line) {
        // Match the declaration that owns a preceding comment block. The summary
        // is keyed by the libui symbol name.
        if (preg_match('/^\s*_UI_EXTERN\b.*?\b(ui[A-Za-z0-9_]+)\s*\(/', $line, $m)) {
            $name = $m[1];
        } elseif (preg_match('/^\s*_UI_ENUM\s*\(\s*(ui[A-Za-z0-9_]+)\s*\)/', $line, $m)) {
            $name = $m[1];
        } else {
            continue;
        }
        if (isset($docs[$name]))
            continue; // first (declaring) occurrence wins

        $summary = extractSummary($lines, $i);
        if ($summary !== '')
            $docs[$name] = $summary;
    }

    return $docs;
}

/**
 * Given the raw header lines and the index of a declaration, look at the
 * comment block on the lines immediately above it and return a one-line
 * summary (or '' when there's nothing usable).
 */
function extractSummary(array $lines, int $declIndex): string
{
    $j = $declIndex - 1;
    if ($j < 0)
        return '';
    $prev = trim($lines[$j]);
    if ($prev === '')
        return ''; // a blank line breaks "immediately preceding"

    // --- doxygen block: the line above ends with `*\/` ----------------------
    if (str_ends_with($prev, '*/')) {
        // Walk up to the matching `/**` (or `/*`) that opens this block.
        $start = null;
        for ($k = $j; $k >= 0; $k--) {
            if (str_contains($lines[$k], '/*')) {
                $start = $k;
                break;
            }
            // Bail out if we wander past a non-comment line (defensive).
            $t = trim($lines[$k]);
            if ($t !== '' && $t[0] !== '*' && ! str_ends_with($t, '*/')) {
                $start = null;
                break;
            }
        }
        if ($start === null)
            return '';
        return summaryFromBlock(array_slice($lines, $start, $j - $start + 1));
    }

    // --- line-comment run: the line above starts with `//` ------------------
    if (str_starts_with($prev, '//')) {
        $block = [];
        for ($k = $j; $k >= 0; $k--) {
            $t = trim($lines[$k]);
            if (! str_starts_with($t, '//'))
                break;
            $block[] = $t;
        }
        return summaryFromLineComments(array_reverse($block));
    }

    return '';
}

/** Extract the first prose line from a doxygen `/** ... *\/` block. */
function summaryFromBlock(array $blockLines): string
{
    foreach ($blockLines as $raw) {
        $t = trim($raw);
        // Strip the comment scaffolding from this physical line.
        $t = preg_replace('#^/\*\*?#', '', $t); // opening /** or /*
        $t = preg_replace('#\*/\s*$#', '', $t); // closing */
        $t = preg_replace('/^\*\s?/', '', $t); // leading " * "
        $t = trim($t);
        if ($t === '')
            continue;
        if ($t[0] === '@')
            break; // reached the @param/@returns tags
        return sanitizeSummary($t);
    }
    return '';
}

/** Extract the first sentence from a run of `// ...` line comments. */
function summaryFromLineComments(array $commentLines): string
{
    $parts = [];
    foreach ($commentLines as $raw) {
        $t = ltrim($raw, '/'); // drop the leading slashes
        $t = trim($t);
        // Skip pure TODO/FIXME maintenance notes — they aren't real summaries.
        if ($t === '' || preg_match('/^(TODO|FIXME|XXX|HACK)\b/', $t)) {
            // Once we've started collecting prose, a TODO ends the summary.
            if ($parts !== [])
                break;
            continue;
        }
        $parts[] = $t;
    }
    if ($parts === [])
        return '';

    $joined = implode(' ', $parts);
    // First sentence: up to the first period that ends it (followed by space/end).
    if (preg_match('/^(.*?[.!?])(?:\s|$)/s', $joined, $m)) {
        $joined = $m[1];
    }
    return sanitizeSummary($joined);
}

/** Collapse whitespace, strip docblock-breakers, and cap the length. */
function sanitizeSummary(string $s): string
{
    $s = preg_replace('/\s+/', ' ', $s);
    $s = str_replace('*/', '', $s); // never let a summary close the docblock early
    $s = trim($s);
    if ($s === '')
        return '';
    if (mb_strlen($s) > 120) {
        $s = rtrim(mb_substr($s, 0, 117)) . '...';
    }
    return $s;
}

/**
 * Render a method/function docblock: a multi-line summary block when one was
 * harvested, otherwise the existing single-line `/** @see X *\/`.
 * $indent is the leading whitespace for the docblock (e.g. '    ' for methods).
 */
function docBlock(string $uiName, array $docs, string $indent): string
{
    $summary = $docs[$uiName] ?? '';
    if ($summary === '') {
        return "{$indent}/** @see {$uiName} */\n";
    }
    return "{$indent}/**\n" . "{$indent} * {$summary}\n" . "{$indent} *\n" . "{$indent} * @see {$uiName}\n" . "{$indent} */\n";
}

// =============================================================================
// 2. Parsing
// =============================================================================

/** The 26 widget types, from ui.h's `#define uiX(this)` cast macros. */
function typeList(string $uiH): array
{
    preg_match_all('/#define\s+(ui[A-Za-z0-9]+)\(this\)/', $uiH, $m);
    $types = array_values(array_unique($m[1]));
    usort($types, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest first
    return $types;
}

/** Parse function prototypes out of the cleaned header. */
function parseFunctions(string $cleaned): array
{
    $flat = preg_replace('/\{[^{}]*\}/s', '{}', $cleaned); // collapse struct/enum bodies
    $funcs = [];
    foreach (explode(';', $flat) as $stmt) {
        $stmt = trim(preg_replace('/\s+/', ' ', $stmt));
        if ($stmt === '' || preg_match('/^(typedef|struct|enum|union)\b/', $stmt))
            continue;
        if (! preg_match('/^(?<ret>.*)\b(?<name>ui[A-Za-z0-9_]+)\s*\((?<params>.*)\)$/', $stmt, $m))
            continue;
        $ret = trim($m['ret']);
        if ($ret === '')
            continue;
        $funcs[$m['name']] = ['name' => $m['name'], 'ret' => $ret, 'params' => parseParams($m['params'])];
    }
    return $funcs;
}

/** Split a C parameter list on top-level commas; classify each param. */
function parseParams(string $s): array
{
    $s = trim($s);
    if ($s === '' || $s === 'void')
        return [];
    $parts = [];
    $depth = 0;
    $buf = '';
    foreach (str_split($s) as $ch) {
        if ($ch === '(')
            $depth++;
        elseif ($ch === ')')
            $depth--;
        if ($ch === ',' && $depth === 0) {
            $parts[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '')
        $parts[] = trim($buf);

    $params = [];
    foreach ($parts as $p) {
        if (str_contains($p, '(*')) { // function-pointer (callback)
            $params[] = ['raw' => $p, 'isCallback' => true, 'type' => $p, 'name' => 'cb'];
        } elseif (preg_match('/^(?<type>.*?)(?<name>[A-Za-z_]\w*)$/', $p, $m) && trim($m['type']) !== '') {
            $params[] = ['raw' => $p, 'isCallback' => false, 'type' => trim($m['type']), 'name' => $m['name']];
        } else {
            $params[] = ['raw' => $p, 'isCallback' => false, 'type' => $p, 'name' => 'arg'];
        }
    }
    return $params;
}

/** Parse the expanded enums: `typedef unsigned int NAME; enum { ... };`. */
function parseEnums(string $cleaned): array
{
    preg_match_all('/typedef unsigned int (ui[A-Za-z0-9]+);\s*enum\s*\{(.*?)\}/s', $cleaned, $m, PREG_SET_ORDER);
    $enums = [];
    foreach ($m as $set) {
        $name = $set[1];
        $members = [];
        $counter = 0;
        foreach (explode(',', $set[2]) as $entry) {
            $entry = trim($entry);
            if ($entry === '')
                continue;
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $entry, $mm)) {
                $value = evalEnumExpr(trim($mm[2]), $members);
                $members[$mm[1]] = $value;
                $counter = $value + 1;
            } else {
                $members[$entry] = $counter++;
            }
        }
        $enums[$name] = $members;
    }
    return $enums;
}

function evalEnumExpr(string $e, array $prior): int
{
    $e = trim($e);
    if (preg_match('/^0x[0-9a-fA-F]+$/', $e))
        return (int) hexdec($e);
    if (preg_match('/^-?\d+$/', $e))
        return (int) $e;
    if (preg_match('/^(\w+)\s*<<\s*(\w+)$/', $e, $m)) {
        return (int) $m[1] << (int) $m[2];
    }
    if (isset($prior[$e]))
        return $prior[$e];
    return 0;
}

// =============================================================================
// 3. Type classification & marshalling
// =============================================================================

function shortName(string $ui): string
{
    return substr($ui, 2);
} // uiButton -> Button

function classify(string $type, array $enums, array $types): array
{
    $t = trim($type);
    $isConst = str_contains($t, 'const');
    $t = trim(str_replace('const', '', $t));
    $stars = substr_count($t, '*');
    $base = trim(preg_replace(['/\*/', '/\bstruct\b/', '/\s+/'], ['', '', ' '], $t));

    if ($stars === 0) {
        if ($base === 'void')
            return ['kind' => 'void'];
        if ($base === 'double')
            return ['kind' => 'double'];
        if (isset($enums[$base]))
            return ['kind' => 'enum', 'name' => $base];
        return ['kind' => 'int']; // int, unsigned int, size_t, uintptr_t, ...
    }
    if ($base === 'char')
        return ['kind' => $isConst ? 'string_borrow' : 'string_owned'];
    if ($base === 'uiControl')
        return ['kind' => 'control'];
    if (in_array($base, $types, true))
        return ['kind' => 'widget', 'name' => $base];
    return ['kind' => 'cdata'];
}

function phpParamType(array $c): string
{
    return match ($c['kind']) {
        'double' => 'float',
        'string_borrow', 'string_owned' => 'string',
        'enum' => '\\Libui\\Generated\\Enum\\' . enumClass($c['name']),
        'control', 'widget' => '\\Libui\\Control',
        'cdata' => '\\FFI\\CData',
        default => 'int',
    };
}

function marshalArg(array $c, string $var): string
{
    // NB: braces required — "$var->handle()" would interpolate $var->handle.
    return match ($c['kind']) {
        'enum' => "{$var}->value",
        'control' => "\\Libui\\Ffi::control({$var}->handle())",
        'widget' => "{$var}->handle()",
        default => $var,
    };
}

/** PHP class name for a libui enum, avoiding reserved words (uiForEach -> UiForEach). */
function enumClass(string $ui): string
{
    $s = shortName($ui);
    return isReservedWord($s) ? 'Ui' . $s : $s;
}

function phpReturnType(array $c, array $generatedTypes): string
{
    return match ($c['kind']) {
        'void' => 'void',
        'double' => 'float',
        'string_borrow', 'string_owned' => 'string',
        'enum' => '\\Libui\\Generated\\Enum\\' . enumClass($c['name']),
        'widget' => in_array($c['name'], $generatedTypes, true)
            ? '\\Libui\\Generated\\' . shortName($c['name'])
            : '\\FFI\\CData',
        'control', 'cdata' => '\\FFI\\CData',
        default => 'int',
    };
}

function returnStmt(array $c, string $call, array $generatedTypes, bool $asBool): string
{
    if ($c['kind'] === 'void')
        return "        {$call};";
    if ($asBool && $c['kind'] === 'int')
        return "        return {$call} !== 0;";
    return match ($c['kind']) {
        'string_owned' => "        return \\Libui\\Ffi::ownedString({$call});",
        'string_borrow' => "        return \\Libui\\Ffi::borrowedString({$call});",
        'enum' => "        return \\Libui\\Generated\\Enum\\" . enumClass($c['name']) . "::from({$call});",
        'widget' => in_array($c['name'], $generatedTypes, true)
            ? "        return \\Libui\\Generated\\" . shortName($c['name']) . "::wrap({$call});"
            : "        return {$call};",
        default => "        return {$call};",
    };
}

// =============================================================================
// 4. Emission
// =============================================================================

function rrmdir(string $dir): void
{
    if (! is_dir($dir))
        return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..')
            continue;
        $p = "{$dir}/{$f}";
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    rmdir($dir);
}

function writeFile(string $path, string $content): void
{
    @mkdir(dirname($path), 0o777, true);
    file_put_contents($path, $content);
}

/** Build one widget method (getter/setter/action/event) or return null to skip. */
function emitMethod(array $fn, string $type, array $ctx): ?string
{
    ['enums' => $enums, 'types' => $types, 'gen' => $gen, 'ann' => $ANN, 'docs' => $docs] = $ctx;

    $name = $fn['name'];
    $rest = substr($name, strlen($type)); // e.g. SetText, OnClicked, Text
    if ($rest === '')
        return null;
    $method = lcfirst($rest);
    $params = $fn['params'];
    $self = array_shift($params); // drop the uiType* self
    $isBool = in_array($name, $ANN['bool_funcs'], true);

    // --- event handlers ------------------------------------------------------
    $hasCallback = (bool) array_filter($params, fn ($p) => $p['isCallback']);
    if (str_starts_with($rest, 'On') && $hasCallback) {
        $dev = $ANN['deviating_callbacks'][$name] ?? null;
        if ($dev === 'int') {
            $body =
                "        \$fn = static::keep(function (\$sender, \$data) use (\$cb) {\n"
                . "            \$r = \$cb(\$this);\n"
                . "            return \$r === false ? 0 : (\\is_int(\$r) ? \$r : 1);\n"
                . '        });';
        } elseif ($dev === 'menuitem') {
            $body = "        \$fn = static::keep(function (\$sender, \$window, \$data) use (\$cb) { \$cb(\$this, \$window); });";
        } else {
            $body = "        \$fn = static::keep(function (\$sender, \$data) use (\$cb) { \$cb(\$this); });";
        }
        return (
            docBlock($name, $docs, '    ')
            . "    public function {$method}(callable \$cb): static\n    {\n"
            . "{$body}\n"
            . "        \\Libui\\Ffi::get()->{$name}(\$this->handle, \$fn, null);\n"
            . "        return \$this;\n    }\n"
        );
    }

    // --- ordinary methods ----------------------------------------------------
    $sig = [];
    $args = ['$this->handle'];
    foreach ($params as $i => $p) {
        $c = classify($p['type'], $enums, $types);
        $pt =
            $isBool && $c['kind'] === 'int' && str_starts_with($rest, 'Set') && $i === (count($params) - 1)
                ? 'bool'
                : phpParamType($c);
        $var = '$' . ($p['name'] ?: "a{$i}");
        $sig[] = "{$pt} {$var}";
        $args[] = $pt === 'bool' ? "(int) {$var}" : marshalArg($c, $var);
    }
    $call = "\\Libui\\Ffi::get()->{$name}(" . implode(', ', $args) . ')';
    $ret = classify($fn['ret'], $enums, $types);

    $isSetter = str_starts_with($rest, 'Set');
    if ($isSetter || $ret['kind'] === 'void') {
        // fluent
        return docBlock($name, $docs, '    ') . "    public function {$method}(" . implode(', ', $sig) . "): static\n    {\n" . "        {$call};\n        return \$this;\n    }\n";
    }

    $rt = $isBool && $ret['kind'] === 'int' ? 'bool' : phpReturnType($ret, $gen);
    return (
        docBlock($name, $docs, '    ')
        . "    public function {$method}("
        . implode(', ', $sig)
        . "): {$rt}\n    {\n"
        . returnStmt($ret, $call, $gen, $isBool && $ret['kind'] === 'int')
        . "\n    }\n"
    );
}

/** Emit one widget class. */
function emitWidget(string $type, array $members, array $ctor, array $ctx): string
{
    ['enums' => $enums, 'types' => $types, 'funcs' => $funcs, 'docs' => $docs] = $ctx;
    $class = shortName($type);
    $methods = [];

    // constructor (primary) + factories
    $primaryFn = $ctor['primary'] ?? null;
    if ($primaryFn && isset($funcs[$primaryFn])) {
        $params = $funcs[$primaryFn]['params'];
        $sig = [];
        $args = [];
        foreach ($params as $i => $p) {
            $c = classify($p['type'], $enums, $types);
            $isHas = str_starts_with($p['name'], 'has') && $c['kind'] === 'int';
            $pt = $isHas ? 'bool' : phpParamType($c);
            $var = '$' . ($p['name'] ?: "a{$i}");
            $sig[] = "{$pt} {$var}";
            $args[] = $isHas ? "(int) {$var}" : marshalArg($c, $var);
        }
        $methods[] =
            docBlock($primaryFn, $docs, '    ')
            . '    public function __construct('
            . implode(', ', $sig)
            . ")\n    {\n"
            . "        \$this->handle = \\Libui\\Ffi::get()->{$primaryFn}("
            . implode(', ', $args)
            . ");\n    }\n";
    }
    foreach ($ctor['factories'] ?? [] as $fm => $fnName) {
        if (! isset($funcs[$fnName]))
            continue;
        $params = $funcs[$fnName]['params'];
        $sig = [];
        $args = [];
        foreach ($params as $i => $p) {
            $c = classify($p['type'], $enums, $types);
            $var = '$' . ($p['name'] ?: "a{$i}");
            $sig[] = phpParamType($c) . " {$var}";
            $args[] = marshalArg($c, $var);
        }
        $methods[] =
            docBlock($fnName, $docs, '    ')
            . "    public static function {$fm}("
            . implode(', ', $sig)
            . "): static\n    {\n"
            . "        return static::wrap(\\Libui\\Ffi::get()->{$fnName}("
            . implode(', ', $args)
            . "));\n    }\n";
    }

    // member methods (skip names already taken by the constructor or factories)
    $seen = ['__construct' => true] + array_fill_keys(array_keys($ctor['factories'] ?? []), true);
    foreach ($members as $fn) {
        $m = emitMethod($fn, $type, $ctx);
        if ($m === null)
            continue;
        if (preg_match('/function (\w+)\(/', $m, $mm)) {
            if (isset($seen[$mm[1]]))
                continue; // skip duplicate method names
            $seen[$mm[1]] = true;
        }
        $methods[] = $m;
    }

    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated;\n\n"
        . "use Libui\\Control;\n\n"
        . "/**\n * GENERATED wrapper for libui `{$type}`. DO NOT EDIT — run `composer regen`.\n"
        . " * Add convenience methods in a hand-written Libui\\\\{$class} subclass instead.\n */\n"
        . "class {$class} extends Control\n{\n"
        . implode("\n", $methods)
        . "}\n"
    );
}

/** Emit a PHP backed enum. */
function emitEnum(string $name, array $members, array $docs = []): string
{
    $class = enumClass($name);
    $prefix = longestCommonPrefix(array_keys($members));
    $cases = [];
    $usedNames = [];
    foreach ($members as $member => $value) {
        $case = substr($member, strlen($prefix));
        if ($case === '' || ctype_digit($case[0]) || isReservedWord($case) || isset($usedNames[strtolower($case)])) {
            $case = shortName($member);
        }
        $usedNames[strtolower($case)] = true;
        $cases[] = "    case {$case} = {$value};";
    }
    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated\\Enum;\n\n" . enumDocBlock($name, $docs) . "enum {$class}: int\n{\n" . implode("\n", $cases) . "\n}\n";
}

/** Render the class docblock for an enum, folding in any harvested summary. */
function enumDocBlock(string $name, array $docs): string
{
    $summary = $docs[$name] ?? '';
    if ($summary === '') {
        return "/** GENERATED from libui `{$name}`. DO NOT EDIT. */\n";
    }
    return "/**\n * {$summary}\n *\n * GENERATED from libui `{$name}`. DO NOT EDIT.\n */\n";
}

/** Emit a bit-flags enum as a const class. */
function emitFlags(string $name, array $members): string
{
    $class = shortName($name);
    $prefix = longestCommonPrefix(array_keys($members));
    $consts = [];
    foreach ($members as $member => $value) {
        $const = substr($member, strlen($prefix)) ?: shortName($member);
        $consts[] = "    public const int {$const} = {$value};";
    }
    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated\\Flags;\n\n"
        . "/** GENERATED bit-flags from libui `{$name}`. DO NOT EDIT. */\n"
        . "final class {$class}\n{\n"
        . implode("\n", $consts)
        . "\n\n"
        . "    public static function has(int \$mask, int \$flag): bool\n    {\n"
        . "        return (\$mask & \$flag) === \$flag;\n    }\n}\n"
    );
}

/** Emit the static facade of free (non-widget) functions. */
function emitFacade(array $fns, array $ctx): string
{
    ['enums' => $enums, 'types' => $types, 'gen' => $gen, 'docs' => $docs] = $ctx;
    $methods = [];
    foreach ($fns as $fn) {
        $method = lcfirst(shortName($fn['name']));
        $sig = [];
        $args = [];
        foreach ($fn['params'] as $i => $p) {
            $c = classify($p['type'], $enums, $types);
            $var = '$' . ($p['name'] ?: "a{$i}");
            $sig[] = phpParamType($c) . " {$var}";
            $args[] = marshalArg($c, $var);
        }
        $call = "\\Libui\\Ffi::get()->{$fn['name']}(" . implode(', ', $args) . ')';
        $ret = classify($fn['ret'], $enums, $types);
        $rt = phpReturnType($ret, $gen);
        $body = returnStmt($ret, $call, $gen, false);
        $methods[] = docBlock($fn['name'], $docs, '    ') . "    public static function {$method}(" . implode(', ', $sig) . "): {$rt}\n    {\n" . "{$body}\n    }\n";
    }
    return (
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace Libui\\Generated;\n\n"
        . "/**\n * GENERATED facade for libui free functions (dialogs, etc.). DO NOT EDIT.\n */\n"
        . "final class Ui\n{\n"
        . implode("\n", $methods)
        . "}\n"
    );
}

// --- small helpers -----------------------------------------------------------

function longestCommonPrefix(array $strings): string
{
    if ($strings === [])
        return '';
    $prefix = $strings[0];
    foreach ($strings as $s) {
        while ($prefix !== '' && ! str_starts_with($s, $prefix)) {
            $prefix = substr($prefix, 0, -1);
        }
    }
    return $prefix;
}

function isReservedWord(string $w): bool
{
    static $rw = [
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
    return in_array(strtolower($w), $rw, true);
}

// =============================================================================
// 5. Main
// =============================================================================

function main(): void
{
    global $ANN;

    if (! is_file(UI_H)) {
        fwrite(STDERR, 'ui.h not found at ' . UI_H . " (run composer build-lib first)\n");
        exit(1);
    }

    // --- clean header ---
    $cleaned = cleanHeader(file_get_contents(UI_H));
    writeFile(GEN_H, $cleaned);

    // --- parse ---
    $uiH = file_get_contents(UI_H);
    $types = typeList($uiH);
    $funcs = parseFunctions($cleaned);
    $enums = parseEnums($cleaned);
    $docs = harvestDocs($uiH); // human-readable summaries from the RAW header

    $skipTypes = $ANN['skip_types'];
    $genTypes = array_values(array_diff($types, $skipTypes)); // types that get a class

    // --- build constructor map: fn => [type, role, method] ---
    $ctorMap = [];
    $ctorFor = [];
    foreach ($genTypes as $T) {
        $cfg = $ANN['constructors'][$T] ?? ['primary' => 'uiNew' . shortName($T), 'factories' => []];
        $ctorFor[$T] = $cfg;
        if ($cfg['primary'])
            $ctorMap[$cfg['primary']] = true;
        foreach ($cfg['factories'] ?? [] as $fn)
            $ctorMap[$fn] = true;
    }

    // --- group member functions by widget type ---
    $byType = array_fill_keys($genTypes, []);
    $free = [];
    foreach ($funcs as $name => $fn) {
        if (isset($ctorMap[$name]))
            continue; // handled as constructor
        $owner = null;
        foreach ($types as $T) { // types sorted longest-first
            if (! (str_starts_with($name, $T) && strlen($name) > strlen($T) && ctype_upper($name[strlen($T)]))) {
                continue;
            }

            $owner = $T;
            break;
        }
        if ($owner === null) {
            $free[$name] = $fn;
            continue;
        }
        if (in_array($owner, $skipTypes, true))
            continue; // uiControl/uiArea/uiTable members
        $byType[$owner][] = $fn;
    }

    // --- emit ---
    rrmdir(GEN_DIR);
    $ctx = ['enums' => $enums, 'types' => $types, 'gen' => $genTypes, 'funcs' => $funcs, 'ann' => $ANN, 'docs' => $docs];

    $widgetCount = $methodCount = $stubCount = 0;
    foreach ($genTypes as $T) {
        $class = shortName($T);
        $php = emitWidget($T, $byType[$T], $ctorFor[$T], $ctx);
        writeFile(GEN_DIR . '/' . $class . '.php', $php);
        $widgetCount++;
        $methodCount += substr_count($php, '    public function ') + substr_count($php, '    public static function ');

        // Scaffold a hand-editable public sugar class ONLY if absent, so the
        // generator never clobbers convenience methods you add later.
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

    $enumCount = 0;
    foreach ($enums as $name => $members) {
        if (in_array($name, $ANN['flag_enums'], true)) {
            writeFile(GEN_DIR . '/Flags/' . shortName($name) . '.php', emitFlags($name, $members));
        } else {
            writeFile(GEN_DIR . '/Enum/' . enumClass($name) . '.php', emitEnum($name, $members, $docs));
        }
        $enumCount++;
    }

    $facadeFns = array_intersect_key($free, array_flip($ANN['facade_funcs']));
    writeFile(GEN_DIR . '/Ui.php', emitFacade($facadeFns, $ctx));

    // Doc-harvest coverage across everything that gets an @see-style docblock.
    $emittedSymbols = array_keys($funcs);
    foreach ($enums as $enumName => $_)
        $emittedSymbols[] = $enumName;
    $emittedSymbols = array_values(array_unique($emittedSymbols));
    $withSummary = count(array_intersect($emittedSymbols, array_keys($docs)));
    $fellBack = count($emittedSymbols) - $withSummary;

    printf(
        "generated:\n  header   %s\n  widgets  %d classes, ~%d methods (+%d new sugar stubs)\n  enums    %d (+%d flags)\n  facade   %d functions\n  docs     %d/%d symbols got a summary (%d fell back to bare @see)\n",
        basename(GEN_H),
        $widgetCount,
        $methodCount,
        $stubCount,
        $enumCount - count($ANN['flag_enums']),
        count($ANN['flag_enums']),
        count($facadeFns),
        $withSummary,
        count($emittedSymbols),
        $fellBack,
    );
}

main();

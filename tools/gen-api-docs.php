<?php

declare(strict_types=1);

/**
 * Generate docs/API.md — a Markdown API reference for the public Libui surface.
 *
 * Reflection-based and dependency-free: phpDocumentor/Doctum can't install on
 * PHP 8.5, and this leans on the rich docblocks the generator already emits.
 * Run with: composer docs:api
 */

require __DIR__ . '/../vendor/autoload.php';

const ROOT = __DIR__ . '/..';

const SRC = ROOT . '/src';

/** Map a src/ file path to its fully-qualified class name. */
function fqcnFromPath(string $path): string
{
    $rel = substr($path, strlen(SRC) + 1, -4); // strip src/ prefix and .php
    return 'Libui\\' . str_replace('/', '\\', $rel);
}

/** First sentence/paragraph of a docblock, cleaned of asterisks and tags. */
function summary(string|false $doc): string
{
    if ($doc === false) {
        return '';
    }
    $lines = [];
    foreach (explode("\n", $doc) as $line) {
        $line = trim($line);
        $line = preg_replace('#^/\*\*+#', '', $line) ?? $line;
        $line = preg_replace('#\*+/$#', '', $line) ?? $line;
        $line = ltrim($line, '* ');
        if (str_starts_with($line, '@')) {
            break; // stop at the first annotation
        }
        if ($line === '' && $lines !== []) {
            break; // first blank line ends the summary paragraph
        }
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    $text = trim(implode(' ', $lines));
    // Resolve inline phpDoc {@see Foo::bar()} cross-references to inline code,
    // since this Markdown is not run through a phpDoc renderer.
    $text = preg_replace('/\{@see\s+([^}]+)\}/', '`$1`', $text) ?? $text;

    return $text;
}

/** Render a parameter list back to a readable PHP-ish signature fragment. */
function renderParams(ReflectionMethod $m): string
{
    $parts = [];
    foreach ($m->getParameters() as $p) {
        $type = $p->getType() !== null ? shortType(typeToString($p->getType())) : '';
        $piece = $type !== '' ? $type . ' ' : '';
        $piece .= ($p->isVariadic() ? '...' : '') . '$' . $p->getName();
        if ($p->isDefaultValueAvailable()) {
            $piece .= ' = ' . renderDefault($p->getDefaultValue());
        }
        $parts[] = $piece;
    }
    return implode(', ', $parts);
}

function typeToString(?ReflectionType $t): string
{
    if ($t === null) {
        return '';
    }
    if ($t instanceof ReflectionNamedType) {
        return ($t->allowsNull() && $t->getName() !== 'null' && $t->getName() !== 'mixed' ? '?' : '') . $t->getName();
    }
    if ($t instanceof ReflectionUnionType) {
        return implode('|', array_map(typeToString(...), $t->getTypes()));
    }
    if ($t instanceof ReflectionIntersectionType) {
        return implode('&', array_map(typeToString(...), $t->getTypes()));
    }
    return (string) $t;
}

/** Strip namespaces from a type string for compact docs. */
function shortType(string $type): string
{
    return preg_replace_callback(
        '#\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*#',
        static function (array $m): string {
            $parts = explode('\\', ltrim($m[0], '\\'));
            return end($parts);
        },
        $type,
    ) ?? $type;
}

function renderDefault(mixed $v): string
{
    if ($v instanceof UnitEnum) {
        return new ReflectionClass($v)->getShortName() . '::' . $v->name;
    }
    return match (true) {
        $v === null => 'null',
        $v === true => 'true',
        $v === false => 'false',
        is_string($v) => "'" . $v . "'",
        is_array($v) => '[]',
        is_float($v) || is_int($v) => (string) $v,
        default => '…',
    };
}

// --- collect classes --------------------------------------------------------

/** @var array<string, list<string>> $groups label => list of FQCNs */
$groups = [
    'Application & lifecycle' => [],
    'Widgets' => [],
    'Containers & layout' => [],
    'Tables' => [],
    'Drawing' => [],
    'Text' => [],
    'Async & utilities' => [],
    'Dialogs' => [],
    'Enums' => [],
];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRC, FilesystemIterator::SKIP_DOTS));
$classes = [];
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, '/Native/')) {
        continue; // raw FFI header dir, no PHP classes
    }
    $fqcn = fqcnFromPath($path);
    if (! class_exists($fqcn) && ! enum_exists($fqcn) && ! interface_exists($fqcn)) {
        continue;
    }
    // The Libui\Generated\ namespace is implementation detail: the public,
    // hand-written Libui\X subclasses are documented instead (with their inherited
    // generated methods folded in). Keep only the public-facing generated bits:
    // the enums/flags and the Ui dialog facade.
    if (str_starts_with($fqcn, 'Libui\\Generated\\')) {
        $keep = str_starts_with($fqcn, 'Libui\\Generated\\Enum\\') || str_starts_with($fqcn, 'Libui\\Generated\\Flags\\') || $fqcn === 'Libui\\Generated\\Ui';
        if (! $keep) {
            continue;
        }
    }
    $classes[$fqcn] = $fqcn;
}

ksort($classes);

foreach ($classes as $fqcn) {
    $short = new ReflectionClass($fqcn)->getShortName();
    $group = match (true) {
        in_array($short, ['App', 'Window', 'Ffi', 'Loop', 'Control'], true) => 'Application & lifecycle',
        in_array($short, ['Box', 'Grid', 'Form', 'Tab', 'Group'], true) => 'Containers & layout',
        in_array($short, ['Table', 'TableModel', 'TableModelDelegate'], true) => 'Tables',
        str_starts_with($fqcn, 'Libui\\Draw\\') || $short === 'Area' || $short === 'AreaDelegate' => 'Drawing',
        str_starts_with($fqcn, 'Libui\\Text\\') => 'Text',
        str_starts_with($fqcn, 'Libui\\Generated\\Enum\\') || str_starts_with($fqcn, 'Libui\\Generated\\Flags\\') => 'Enums',
        str_starts_with($fqcn, 'Libui\\Utils\\') || $short === 'Image' => 'Async & utilities',
        $short === 'Ui' => 'Dialogs',
        default => 'Widgets',
    };
    $groups[$group][] = $fqcn;
}

// --- render -----------------------------------------------------------------

$out = [];
$out[] = '# Libui for PHP — API Reference';
$out[] = '';
$out[] = '> Generated from source by `composer docs:api` (`tools/gen-api-docs.php`).';
$out[] = '> For a narrative walkthrough see [GUIDE.md](GUIDE.md); for design see';
$out[] = '> [ARCHITECTURE.md](ARCHITECTURE.md).';
$out[] = '';
$out[] = '## Contents';
$out[] = '';
foreach ($groups as $label => $fqcns) {
    if ($fqcns === []) {
        continue;
    }
    $anchor = strtolower(preg_replace('#[^a-z0-9]+#i', '-', $label) ?? $label);
    $out[] = "- [{$label}](#{$anchor})";
}
$out[] = '';

$enumGroup = 'Enums';
foreach ($groups as $label => $fqcns) {
    if ($fqcns === []) {
        continue;
    }
    sort($fqcns);
    $out[] = '## ' . $label;
    $out[] = '';

    if ($label === $enumGroup) {
        // Compact rendering for enums: name + cases.
        foreach ($fqcns as $fqcn) {
            $rc = new ReflectionClass($fqcn);
            $cases = [];
            if ($rc->isEnum()) {
                foreach (new ReflectionEnum($fqcn)->getCases() as $case) {
                    $cases[] = $case->getName();
                }
            }
            $out[] = '- **`' . $rc->getShortName() . '`** — ' . ($cases === [] ? 'flags/constants' : implode(', ', $cases));
        }
        $out[] = '';
        continue;
    }

    foreach ($fqcns as $fqcn) {
        $rc = new ReflectionClass($fqcn);
        $out[] = '### `' . $rc->getShortName() . '`';
        $out[] = '';
        $parent = $rc->getParentClass();
        // A Libui\Generated\ parent is implementation detail (its methods are folded
        // in below); only surface a meaningful, non-generated parent like Control.
        $parentNote = $parent && ! str_starts_with($parent->getName(), 'Libui\\Generated\\')
            ? ' — extends `' . $parent->getShortName() . '`'
            : '';
        $out[] = '`' . $fqcn . '`' . $parentNote;
        $out[] = '';
        $classSummary = summary($rc->getDocComment());
        if ($classSummary !== '') {
            $out[] = $classSummary;
            $out[] = '';
        }

        // Document methods declared on this class or on its generated parent
        // (Libui\Generated\X), but not the shared Control verbs — those live once
        // under Control. This folds the generated API into the public subclass.
        $own = array_filter(
            $rc->getMethods(ReflectionMethod::IS_PUBLIC),
            static function (ReflectionMethod $m) use ($rc): bool {
                $decl = $m->getDeclaringClass()->getName();
                if ($decl === 'Libui\\Control') {
                    return false;
                }
                return $decl === $rc->getName() || str_starts_with($decl, 'Libui\\Generated\\');
            },
        );
        // De-duplicate by method name (a subclass override and its parent both match).
        $byName = [];
        foreach ($own as $m) {
            $byName[$m->getName()] ??= $m;
        }
        $own = array_values($byName);
        usort($own, static fn ($a, $b) => $a->isStatic() === $b->isStatic() ? strcmp($a->getName(), $b->getName()) : ($a->isStatic() ? -1 : 1));

        $inheritsControl = $rc->isSubclassOf('Libui\\Control');
        if ($own === []) {
            $out[] = $inheritsControl ? '_Inherits the common widget verbs from `Control`._' : '_No public methods._';
            $out[] = '';
            continue;
        }
        if ($inheritsControl) {
            $out[] = '_Plus the common widget verbs from [`Control`](#control)._';
            $out[] = '';
        }

        foreach ($own as $m) {
            if ($m->getName() === '__destruct') {
                continue;
            }
            $sig = ($m->isStatic() ? 'static ' : '') . $m->getName() . '(' . renderParams($m) . ')';
            $ret = typeToString($m->getReturnType());
            if ($ret !== '') {
                $sig .= ': ' . shortType($ret);
            }
            $line = '- `' . $sig . '`';
            $ms = summary($m->getDocComment());
            if ($ms !== '') {
                $line .= ' — ' . $ms;
            }
            $out[] = $line;
        }
        $out[] = '';
    }
}

$target = ROOT . '/docs/API.md';
file_put_contents($target, implode("\n", $out) . "\n");

$classCount = count($classes);
echo "Wrote docs/API.md ({$classCount} classes across " . count(array_filter($groups)) . " groups).\n";

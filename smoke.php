<?php
// Non-GUI smoke test: proves the FFI binding loads and libui initialises.
// Does NOT enter uiMain(), so it returns immediately instead of blocking.
declare(strict_types=1);
require __DIR__ . '/src/Ui.php';

$ui = new Ui();
echo "FFI loaded, header parsed.\n";

$ui->init();
echo "uiInit() OK.\n";

$win    = $ui->uiNewWindow('smoke', 200, 100, 0);
$box    = $ui->uiNewVerticalBox();
$button = $ui->uiNewButton('x');
echo 'uiNewWindow null? ' . var_export(\FFI::isNull($win), true) . "\n";
echo 'uiNewButton null? ' . var_export(\FFI::isNull($button), true) . "\n";

// Bind a closure as a C callback to confirm FFI callbacks work in this build.
$ui->uiButtonOnClicked($button, $ui->keepCallback(function ($s, $d) {}), null);
echo "uiButtonOnClicked callback bound OK.\n";

$ui->uiBoxAppend($box, $ui->control($button), 0);
$ui->uiWindowSetChild($win, $ui->control($box));
echo "controls wired (casts to uiControl* OK).\n";

echo "SMOKE OK\n";

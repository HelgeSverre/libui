<?php

declare(strict_types=1);

/**
 * A "poor man's Ray" (à la spatie/ray): a native window that receives variable
 * dumps over a local TCP socket and shows them live in a table.
 *
 * Run this receiver, then fire dumps at it from any PHP process via the helper:
 *   php examples/ray.php          # window 1: the receiver
 *   php examples/ray-helper.php   # window 2: sends a few sample dumps
 *
 * The receiver opens a non-blocking TCP server on 127.0.0.1:9919. A libui timer
 * polls it ~20x/sec, accepts any pending client, reads one line of JSON
 * ({type, value, caller, time}), appends it to the model's row array, and calls
 * rowInserted() so the Table repaints — dumps from a *separate* process appear
 * in the grid in real time.
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Ffi;
use Libui\Label;
use Libui\Table;
use Libui\TableModelDelegate;
use Libui\Window;

const RAY_HOST = '127.0.0.1';

const RAY_PORT = 9919;

Ffi::init();

// The model: a growing array of [time, type, value, caller] rows. The timer
// pushes new rows onto $rows and notifies libui; cellValue() reads them back.
$delegate = new class extends TableModelDelegate {
    /** @var array<int, array{string,string,string,string}> Time / Type / Value / Caller */
    public array $rows = [];

    public function numColumns(): int
    {
        return 4;
    }

    public function numRows(): int
    {
        return \count($this->rows);
    }

    public function cellValue(int $row, int $column): string|int
    {
        return $this->rows[$row][$column] ?? '';
    }
};

$table = Table::fromDelegate($delegate)
    ->appendTextColumn('Time', 0)
    ->appendTextColumn('Type', 1)
    ->appendTextColumn('Value', 2)
    ->appendTextColumn('Caller', 3);

$table->setColumnWidth(0, 80);
$table->setColumnWidth(1, 90);
$table->setColumnWidth(2, 320);
$table->setColumnWidth(3, 220);

$model = $table->model();

// Non-blocking TCP server. If the port is taken we still build the UI so the
// window is visible; the status label reports the failure.
$status = new Label('listening on ' . RAY_HOST . ':' . RAY_PORT);
$errno = 0;
$errstr = '';
$server = @stream_socket_server('tcp://' . RAY_HOST . ':' . RAY_PORT, $errno, $errstr);
if ($server === false) {
    $status->setText('could not listen on ' . RAY_HOST . ':' . RAY_PORT . " — {$errstr}");
} else {
    stream_set_blocking($server, false);
}

// Poll the socket on the UI thread. One connection carries one or more
// newline-delimited JSON dumps; each becomes a row.
Ffi::timer(50, function () use ($server, $delegate, $model): bool {
    if ($server === false) {
        return true; // keep the timer alive even with no server, so the UI runs
    }

    // Drain every client that has connected since the last tick.
    while (($client = @stream_socket_accept($server, 0)) !== false) {
        stream_set_blocking($client, false);

        // Give the sender a brief moment to flush its payload, then read it all.
        $payload = '';
        $deadline = microtime(true) + 0.05;
        while (microtime(true) < $deadline) {
            $chunk = @fread($client, 65536);
            if ($chunk === false || $chunk === '') {
                if (feof($client)) {
                    break;
                }
                usleep(1000);
                continue;
            }
            $payload .= $chunk;
        }
        @fclose($client);

        foreach (explode("\n", trim($payload)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $msg = json_decode($line, true);
            if (! is_array($msg)) {
                continue;
            }

            $delegate->rows[] = [
                (string) ($msg['time'] ?? ''),
                (string) ($msg['type'] ?? ''),
                (string) ($msg['value'] ?? ''),
                (string) ($msg['caller'] ?? ''),
            ];
            // Tell libui a row appeared at the end so the Table repaints live.
            $model->rowInserted(\count($delegate->rows) - 1);
        }
    }

    return true;
});

$window = new Window('Ray — PHP debug dumps', 760, 420);
$box = new Box();
$box->append($status); // status line on top
$box->appendStretchy($table); // table fills the rest
$window->setChild($box);

// Free the model after the loop returns (the table — and its window — are gone
// by then); freeing earlier or never both abort libui in uiUninit().
$window->run(afterClose: function () use ($model, $server) {
    if ($server !== false) {
        @fclose($server);
    }
    $model->free();
});

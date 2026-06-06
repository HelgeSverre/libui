<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\Box;
use Libui\Button;
use Libui\Entry;
use Libui\Ffi;
use Libui\Label;
use Libui\Loop;
use Libui\Window;

// This example demonstrates non-blocking HTTP requests in a GUI application.
// The GUI remains responsive while HTTP requests are in flight.
//
// NOTE: This example requires the file_get_contents() with stream wrapper for HTTP,
// or cURL extension for async HTTP requests. The actual async implementation
// would typically use a proper HTTP client like:
//
//   - Guzzle with a custom handler that integrates with Loop
//   - ReactPHP HTTP client
//   - Amp HTTP client
//
// For simplicity, this example simulates async HTTP using Loop::delay().

Ffi::init();

$window = new Window('Async HTTP Demo', 400, 300, true);

$box = new Box();
$box->setPadded(true);

$label = new Label('Click "Fetch" to make a non-blocking HTTP request');
$entry = new Entry();
$entry->setText('https://httpbin.org/get');

$button = new Button('Fetch');
$status = new Label('Ready');

$box->append($label);
$box->append($entry);
$box->append($button);
$box->append($status);

$window->setChild($box);

// Simulate an async HTTP request
$button->onClicked(function () use ($entry, $status): void {
    $url = $entry->text();
    $status->setText('Requesting...');

    // In a real implementation, this would use a proper async HTTP client.
    // Here we simulate it with a delay to show the GUI doesn't freeze.
    Loop::delay(1000, function () use ($url, $status): void {
        // This callback runs on the main thread after 1 second
        // In a real app, this would be the HTTP response callback

        // Simulate fetching data
        $response = simulateHttpRequest($url);

        // Update the UI on the main thread
        Loop::defer(function () use ($status, $response): void {
            $status->setText('Success! Received ' . \strlen($response) . ' bytes');
        });
    });
});

$window->onClosing(function (): bool {
    Loop::stop();
    return true;
});

$window->show();
Loop::run();

/**
 * Simulate an HTTP request (synchronous, for demonstration).
 * In a real async implementation, this would use a non-blocking HTTP client.
 */
function simulateHttpRequest(string $url): string
{
    // In a real app, you would use:
    // - file_get_contents() with stream context (blocking, but in a Loop::delay)
    // - cURL with CURLOPT_NOSIGNAL
    // - ReactPHP\Http\Browser
    // - Amp\Http\Client

    // For this demo, we just return a mock response
    sleep(1); // Simulate network delay
    return 'Mock response from ' . $url;
}

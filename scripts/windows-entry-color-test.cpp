// Native EDIT rendering regression test. Run on an interactive Windows desktop:
// cl /EHsc /I third_party\libui-ng scripts\windows-entry-color-test.cpp user32.lib gdi32.lib
// windows-entry-color-test.exe lib\windows-x86_64\libui.dll
#include <windows.h>
#include <cstdio>
#include <cstring>
#include <vector>
#include <string>
#include <ui.h>

static HMODULE module;
#define UI(name) reinterpret_cast<decltype(&name)>(GetProcAddress(module, #name))
static uiWindow *window;
static uiEntry *entry, *password, *dynamicMask;
static uiMultilineEntry *multiline;
static int failures = 0, changes = 0;

static void check(bool result, const char *name)
{
	std::printf("%s %s\n", result ? "PASS" : "FAIL", name);
	if (!result) ++failures;
}

static HWND hwnd(uiControl *control) { return (HWND) UI(uiControlHandle)(control); }

// Measure yellow face pixels, excluding white/gray backgrounds and blue selection.
// Enough yellow pixels must exist to distinguish a color face from ClearType
// fringes around a monochrome glyph. Capture the actual native EDIT painter.
static int yellowScore(HWND control)
{
	RECT rect;
	if (!GetClientRect(control, &rect) || rect.right <= 0 || rect.bottom <= 0) return -1;
	HDC screen = GetDC(control);
	HDC dc = CreateCompatibleDC(screen);
	HBITMAP bitmap = CreateCompatibleBitmap(screen, rect.right, rect.bottom);
	HGDIOBJ old = SelectObject(dc, bitmap);
	HFONT font = (HFONT) SendMessageW(control, WM_GETFONT, 0, 0);
	HGDIOBJ oldFont = SelectObject(dc, font);
	TEXTMETRICW metrics;
	GetTextMetricsW(dc, &metrics);
	FillRect(dc, &rect, (HBRUSH) GetStockObject(WHITE_BRUSH));
	RedrawWindow(control, NULL, NULL, RDW_INVALIDATE | RDW_ERASE | RDW_UPDATENOW);
	BOOL copied = BitBlt(dc, 0, 0, rect.right, rect.bottom, screen, 0, 0, SRCCOPY);
	SelectObject(dc, oldFont);
	SelectObject(dc, old);
	BITMAPINFO info = {};
	info.bmiHeader.biSize = sizeof(BITMAPINFOHEADER);
	info.bmiHeader.biWidth = rect.right;
	info.bmiHeader.biHeight = -rect.bottom;
	info.bmiHeader.biPlanes = 1;
	info.bmiHeader.biBitCount = 32;
	std::vector<unsigned char> pixels(rect.right * rect.bottom * 4);
	int lines = GetDIBits(dc, bitmap, 0, rect.bottom, pixels.data(), &info, DIB_RGB_COLORS);
	DeleteObject(bitmap);
	DeleteDC(dc);
	ReleaseDC(control, screen);
	if (!copied || lines != rect.bottom) { std::printf("Capture failed: %d/%ld rows (%lu)\n", lines, rect.bottom, GetLastError()); return -1; }
	int yellow = 0;
	// Check the first visible line, so adding more monochrome lines cannot
	// accumulate enough ClearType fringes to produce a false positive.
	int firstLine = (GetWindowLongPtrW(control, GWL_STYLE) & ES_MULTILINE) ? (int) SendMessageW(control, EM_GETFIRSTVISIBLELINE, 0, 0) : 0;
	int firstCharacter = (int) SendMessageW(control, EM_LINEINDEX, firstLine, 0);
	int top = (SHORT) HIWORD(SendMessageW(control, EM_POSFROMCHAR, firstCharacter, 0));
	if (top < 0) top = 0;
	int bottom = top + metrics.tmHeight;
	if (bottom > rect.bottom) bottom = rect.bottom;
    auto yellowAt = [&](int x, int y) {
		size_t i = ((size_t) y * rect.right + x) * 4;
		return pixels[i + 2] > 180 && pixels[i + 1] > 100 && pixels[i] < 100;
	};
	// Color glyphs have filled regions. Requiring a 2x2 yellow block excludes
	// thin ClearType fringes even when the viewport contains many Latin letters.
	for (int y = top; y + 1 < bottom; ++y)
		for (int x = 0; x + 1 < rect.right; ++x)
			if (yellowAt(x, y) && yellowAt(x + 1, y) && yellowAt(x, y + 1) && yellowAt(x + 1, y + 1)) ++yellow;
	return yellow;
}

static int runTests(void *)
{
	check(yellowScore(hwnd(uiControl(entry))) > 0, "single-line entry paints color emoji");
	check(yellowScore(hwnd(uiControl(multiline))) > 0, "multiline entry paints color emoji");
	UI(uiEntrySetReadOnly)(entry, 1);
	check(yellowScore(hwnd(uiControl(entry))) > 0, "read-only entry paints color emoji");
	UI(uiEntrySetReadOnly)(entry, 0);
	SendMessageW(hwnd(uiControl(entry)), EM_SETSEL, 0, 2);
	check(yellowScore(hwnd(uiControl(entry))) > 0, "unselected emoji stay colored beside a selection");
	SendMessageW(hwnd(uiControl(entry)), EM_SETSEL, 0, 5);
	check(yellowScore(hwnd(uiControl(entry))) == 0, "selected glyphs retain native selection painting");
	check(yellowScore(hwnd(uiControl(password))) == 0, "password entry never paints underlying emoji");
	SendMessageW(hwnd(uiControl(dynamicMask)), EM_SETPASSWORDCHAR, L'*', 0);
	check(yellowScore(hwnd(uiControl(dynamicMask))) == 0, "mask enabled after construction never reveals emoji");
	SendMessageW(hwnd(uiControl(dynamicMask)), EM_SETPASSWORDCHAR, 0, 0);
	check(yellowScore(hwnd(uiControl(dynamicMask))) > 0, "unmasked entry restores color painting");
	SendMessageW(hwnd(uiControl(multiline)), EM_LINESCROLL, 0, 5);
	check(SendMessageW(hwnd(uiControl(multiline)), EM_GETFIRSTVISIBLELINE, 0, 0) > 0, "native multiline scrolling works");
	check(yellowScore(hwnd(uiControl(multiline))) > 0, "scrolled multiline text keeps color emoji");
	SendMessageW(hwnd(uiControl(entry)), WM_CHAR, L'A', 0);
	char *text = UI(uiEntryText)(entry);
	check(std::strcmp(text, "A") == 0 && changes == 1, "native editing triggers one change callback");
	UI(uiFreeText)(text);
	UI(uiEntrySetText)(entry, "\xF0\x9F\x98\x83 \xF0\x9F\x98\x83");
	check(yellowScore(hwnd(uiControl(entry))) > 0 && changes == 1, "painting and programmatic setters do not trigger change callbacks");
    HDC measure = GetDC(hwnd(uiControl(entry)));
	HFONT font = (HFONT) SendMessageW(hwnd(uiControl(entry)), WM_GETFONT, 0, 0);
	HGDIOBJ priorFont = SelectObject(measure, font);
	SIZE width;
	GetTextExtentPoint32W(measure, L"M", 1, &width);
	SelectObject(measure, priorFont);
	ReleaseDC(hwnd(uiControl(entry)), measure);
	std::string longLine((65536 + width.cx - 1) / width.cx, 'M');
	longLine += "\xF0\x9F\x98\x83";
	UI(uiEntrySetText)(entry, longLine.c_str());
	SendMessageW(hwnd(uiControl(entry)), EM_SETSEL, 0, 0);
	SendMessageW(hwnd(uiControl(entry)), EM_SCROLLCARET, 0, 0);
	check(yellowScore(hwnd(uiControl(entry))) == 0, "offscreen emoji do not wrap into a long line's visible text");
	int end = GetWindowTextLengthW(hwnd(uiControl(entry)));
	SendMessageW(hwnd(uiControl(entry)), EM_SETSEL, end, end);
	SendMessageW(hwnd(uiControl(entry)), EM_SCROLLCARET, 0, 0);
	check(yellowScore(hwnd(uiControl(entry))) > 0, "long-line emoji render after horizontal scrolling");
	UI(uiQuit)();
	return 0;
}

int main(int argc, char **argv)
{
	if (argc != 2) { std::fprintf(stderr, "Usage: windows-entry-color-test.exe <libui.dll>\n"); return 2; }
	module = LoadLibraryA(argv[1]);
	if (module == NULL) { std::fprintf(stderr, "Cannot load %s (%lu)\n", argv[1], GetLastError()); return 2; }
	const char *symbols[] = {"uiInit", "uiNewWindow", "uiNewEntry", "uiNewPasswordEntry", "uiNewMultilineEntry", "uiNewVerticalBox", "uiControlHandle", "uiControlShow", "uiControlDestroy", "uiWindowSetChild", "uiEntrySetText", "uiEntryText", "uiFreeText", "uiEntrySetReadOnly", "uiEntryOnChanged", "uiMultilineEntrySetText", "uiBoxAppend", "uiTimer", "uiMain", "uiQuit", "uiUninit", "uiFreeInitError"};
	for (const char *symbol : symbols) {
		if (GetProcAddress(module, symbol) == NULL) { std::fprintf(stderr, "Missing export: %s\n", symbol); return 2; }
	}
	uiInitOptions options = {};
	options.Size = sizeof options;
	const char *error = UI(uiInit)(&options);
	if (error != NULL) { std::fprintf(stderr, "%s\n", error); UI(uiFreeInitError)(error); return 2; }
	entry = UI(uiNewEntry)();
	password = UI(uiNewPasswordEntry)();
	dynamicMask = UI(uiNewEntry)();
	multiline = UI(uiNewMultilineEntry)();
	const char *faces = "\xF0\x9F\x98\x83 \xF0\x9F\x98\x83";
	UI(uiEntrySetText)(entry, faces);
	UI(uiEntrySetText)(password, faces);
	UI(uiEntrySetText)(dynamicMask, faces);
	UI(uiEntryOnChanged)(entry, [](uiEntry *, void *) { ++changes; }, NULL);
	std::string lines;
	for (int i = 0; i < 20; ++i) { lines += faces; lines += '\n'; }
	UI(uiMultilineEntrySetText)(multiline, lines.c_str());
	uiBox *box = UI(uiNewVerticalBox)();
	UI(uiBoxAppend)(box, uiControl(entry), 0);
	UI(uiBoxAppend)(box, uiControl(password), 0);
	UI(uiBoxAppend)(box, uiControl(dynamicMask), 0);
	UI(uiBoxAppend)(box, uiControl(multiline), 1);
	window = UI(uiNewWindow)("Native entry color tests", 400, 180, 0);
	UI(uiWindowSetChild)(window, uiControl(box));
	UI(uiControlShow)(uiControl(window));
	// CLI hosts may inherit SW_HIDE; tests need an actual visible control tree.
	ShowWindow(hwnd(uiControl(window)), SW_SHOW);
	SetWindowPos(hwnd(uiControl(window)), HWND_TOPMOST, 0, 0, 0, 0, SWP_NOMOVE | SWP_NOSIZE);
	UI(uiTimer)(100, runTests, NULL);
	UI(uiMain)();
	UI(uiControlDestroy)(uiControl(window));
	UI(uiUninit)();
	FreeLibrary(module);
	return failures ? 1 : 0;
}

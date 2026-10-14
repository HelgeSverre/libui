/* Native ownership/text smoke test for Linux ASan + UBSan builds.
 * Build with cc -fsanitize=address,undefined -Ithird_party/libui-ng ... libui.so.
 * Run under xvfb with G_DEBUG=fatal-warnings and ASAN_OPTIONS=detect_leaks=0.
 * LeakSanitizer is disabled because GTK keeps process-global allocations.
 */
#include <assert.h>
#include <stdio.h>
#include <string.h>
#include <ui.h>

static int stale_timer_calls;

static int stale_timer(void *data)
{
	++stale_timer_calls;
	return 0;
}

static int stop_timer(void *data)
{
	uiQuit();
	return 0;
}

int main(void)
{
	uiInitOptions options = {0};
	options.Size = sizeof options;
	const char *error = uiInit(&options);
	if (error != NULL) {
		fprintf(stderr, "uiInit: %s\n", error);
		uiFreeInitError(error);
		return 1;
	}
	for (int iteration = 0; iteration < 200; ++iteration) {
		uiBox *first = uiNewVerticalBox();
		uiBox *second = uiNewVerticalBox();
		uiButton *button = uiNewButton("child");
		uiBoxAppend(first, uiControl(button), 0);
		uiBoxDelete(first, 0);
		uiBoxAppend(second, uiControl(button), 0);
		uiControlDestroy(uiControl(first)); /* never parented */
		uiControlDestroy(uiControl(second)); /* destroys its child */

		uiWindow *window = uiNewWindow("Ownership", 100, 100, 0);
		uiGroup *group = uiNewGroup("Group");
		uiTab *tab = uiNewTab();
		uiEntry *detached = uiNewEntry();
		uiTabAppend(tab, "Page", uiControl(detached));
		uiTabDelete(tab, 0);
		uiEntrySetText(detached, "detached and still live");
		uiGroupSetChild(group, uiControl(tab));
		uiWindowSetChild(window, uiControl(group));
		uiControlDestroy(uiControl(window));
		uiControlDestroy(uiControl(detached));

		uiAttributedString *text = uiNewAttributedString("A\xF0\x9F\x98\x80Z");
		uiAttributedStringInsertAtUnattributed(text, "\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x92\xBB", 5);
		uiAttributedStringDelete(text, 1, 5);
		assert(strcmp(uiAttributedStringString(text), "A\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x92\xBBZ") == 0);
		uiAttributedStringSetAttribute(text, uiNewSizeAttribute(14), 0, 1);
		uiAttributedStringSetAttribute(text, uiNewSizeAttribute(14), 1, uiAttributedStringLen(text));
		uiFontDescriptor font = {0};
		font.Family = "Sans";
		font.Size = 14;
		font.Weight = uiTextWeightNormal;
		font.Italic = uiTextItalicNormal;
		font.Stretch = uiTextStretchNormal;
		uiDrawTextLayoutParams params = {0};
		params.String = text;
		params.DefaultFont = &font;
		params.Width = 200;
		params.Align = uiDrawTextAlignLeft;
		uiDrawTextLayout *layout = uiDrawNewTextLayout(&params);
		double width, height;
		uiDrawTextLayoutExtents(layout, &width, &height);
		assert(width >= 0 && height > 0);
		uiDrawFreeTextLayout(layout);
		uiFreeAttributedString(text);
		uiFreeAttribute(uiNewSizeAttribute(14));
		uiDrawPath *path = uiDrawNewPath(uiDrawFillModeWinding);
		uiDrawPathAddRectangle(path, 0, 0, 10, 10);
		uiDrawPathEnd(path);
		uiDrawFreePath(path);
	}
	/* uiUninit must remove the GLib source before freeing its timer data. */
	uiTimer(1, stale_timer, NULL);
	uiUninit();
	error = uiInit(&options);
	assert(error == NULL);
	uiTimer(10, stop_timer, NULL);
	uiMain();
	assert(stale_timer_calls == 0);
	uiUninit();
	puts("PASS: 200 native ownership/text cycles and timer source teardown");
	return 0;
}

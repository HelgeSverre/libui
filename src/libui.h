/*
 * Minimal C declarations for libui-ng, consumed by PHP's FFI::cdef().
 * Only the subset used by this demo is declared. This file is NOT compiled
 * by a C compiler; PHP's FFI parser reads it directly.
 *
 * Each libui object (uiWindow, uiButton, ...) is an opaque struct. libui
 * treats them as subclasses of uiControl, so we cast to "uiControl *" when
 * passing them to the generic control functions.
 */

/* ---- lifecycle ---- */
typedef struct uiInitOptions uiInitOptions;
struct uiInitOptions { size_t Size; };
const char *uiInit(uiInitOptions *options);
void uiUninit(void);
void uiMain(void);
void uiQuit(void);

/* ---- base control ---- */
typedef struct uiControl uiControl;
void uiControlShow(uiControl *c);
void uiControlDestroy(uiControl *c);

/* ---- window ---- */
typedef struct uiWindow uiWindow;
uiWindow *uiNewWindow(const char *title, int width, int height, int hasMenubar);
void uiWindowSetChild(uiWindow *w, uiControl *child);
void uiWindowSetMargined(uiWindow *w, int margined);
void uiWindowSetTitle(uiWindow *w, const char *title);
void uiWindowOnClosing(uiWindow *w, int (*f)(uiWindow *sender, void *senderData), void *data);

/* ---- layout box ---- */
typedef struct uiBox uiBox;
uiBox *uiNewVerticalBox(void);
uiBox *uiNewHorizontalBox(void);
void uiBoxAppend(uiBox *b, uiControl *child, int stretchy);
void uiBoxSetPadded(uiBox *b, int padded);

/* ---- label ---- */
typedef struct uiLabel uiLabel;
uiLabel *uiNewLabel(const char *text);
void uiLabelSetText(uiLabel *l, const char *text);

/* ---- entry (single-line text field) ---- */
typedef struct uiEntry uiEntry;
uiEntry *uiNewEntry(void);
char *uiEntryText(uiEntry *e);
void uiEntrySetText(uiEntry *e, const char *text);

/* ---- button ---- */
typedef struct uiButton uiButton;
uiButton *uiNewButton(const char *text);
void uiButtonOnClicked(uiButton *b, void (*f)(uiButton *sender, void *senderData), void *data);

/* ---- misc ---- */
void uiFreeText(char *text);

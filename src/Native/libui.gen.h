// GENERATED from libui-ng ui.h by tools/generate.php — DO NOT EDIT.
// Re-run `composer regen` to regenerate.

typedef unsigned int uiForEach; enum {
	uiForEachContinue,
	uiForEachStop,
};

typedef struct uiInitOptions uiInitOptions;

struct uiInitOptions {
	size_t Size;
};

const char *uiInit(uiInitOptions *options);
void uiUninit(void);
void uiFreeInitError(const char *err);

void uiMain(void);
void uiMainSteps(void);
int uiMainStep(int wait);
void uiQuit(void);

void uiQueueMain(void (*f)(void *data), void *data);

void uiTimer(int milliseconds, int (*f)(void *data), void *data);

void uiOnShouldQuit(int (*f)(void *data), void *data);

void uiFreeText(char *text);

typedef struct uiControl uiControl;
struct uiControl {
	uint32_t Signature;
	uint32_t OSSignature;
	uint32_t TypeSignature;
	void (*Destroy)(uiControl *);
	uintptr_t (*Handle)(uiControl *);
	uiControl *(*Parent)(uiControl *);
	void (*SetParent)(uiControl *, uiControl *);
	int (*Toplevel)(uiControl *);
	int (*Visible)(uiControl *);
	void (*Show)(uiControl *);
	void (*Hide)(uiControl *);
	int (*Enabled)(uiControl *);
	void (*Enable)(uiControl *);
	void (*Disable)(uiControl *);
};

void uiControlDestroy(uiControl *c);

uintptr_t uiControlHandle(uiControl *c);

uiControl *uiControlParent(uiControl *c);

void uiControlSetParent(uiControl *c, uiControl *parent);

int uiControlToplevel(uiControl *c);

int uiControlVisible(uiControl *c);

void uiControlShow(uiControl *c);

void uiControlHide(uiControl *c);

int uiControlEnabled(uiControl *c);

void uiControlEnable(uiControl *c);

void uiControlDisable(uiControl *c);

uiControl *uiAllocControl(size_t n, uint32_t OSsig, uint32_t typesig, const char *typenamestr);

void uiFreeControl(uiControl *c);

void uiControlVerifySetParent(uiControl *c, uiControl *parent);

int uiControlEnabledToUser(uiControl *c);

void uiUserBugCannotSetParentOnToplevel(const char *type);

typedef struct uiWindow uiWindow;

char *uiWindowTitle(uiWindow *w);

void uiWindowSetTitle(uiWindow *w, const char *title);

void uiWindowPosition(uiWindow *w, int *x, int *y);

void uiWindowSetPosition(uiWindow *w, int x, int y);

void uiWindowOnPositionChanged(uiWindow *w,
	void (*f)(uiWindow *sender, void *senderData), void *data);

void uiWindowContentSize(uiWindow *w, int *width, int *height);

void uiWindowSetContentSize(uiWindow *w, int width, int height);

int uiWindowFullscreen(uiWindow *w);

void uiWindowSetFullscreen(uiWindow *w, int fullscreen);

void uiWindowOnContentSizeChanged(uiWindow *w,
	void (*f)(uiWindow *sender, void *senderData), void *data);

void uiWindowOnClosing(uiWindow *w,
	int (*f)(uiWindow *sender, void *senderData), void *data);

void uiWindowOnFocusChanged(uiWindow *w,
	void (*f)(uiWindow *sender, void *senderData), void *data);

int uiWindowFocused(uiWindow *w);

int uiWindowBorderless(uiWindow *w);

void uiWindowSetBorderless(uiWindow *w, int borderless);

void uiWindowSetChild(uiWindow *w, uiControl *child);

int uiWindowMargined(uiWindow *w);

void uiWindowSetMargined(uiWindow *w, int margined);

int uiWindowResizeable(uiWindow *w);

void uiWindowSetResizeable(uiWindow *w, int resizeable);

int uiWindowKeepAbove(const uiWindow *w);

void uiWindowSetKeepAbove(uiWindow *w, int keepAbove);

typedef unsigned int uiWindowCornerStyle; enum {
	uiWindowCornerStyleNone,        
	uiWindowCornerStyleRounded,     
	uiWindowCornerStyleRoundedSmall 
};

void uiWindowSetTitlebar(uiWindow *w, uiControl *titlebar);

uiWindowCornerStyle uiWindowGetCornerStyle(uiWindow *w);

void uiWindowSetCornerStyle(uiWindow *w, uiWindowCornerStyle style);

int uiWindowShadow(uiWindow *w);

void uiWindowSetShadow(uiWindow *w, int shadow);

uiWindow *uiNewWindow(const char *title, int width, int height, int hasMenubar);

typedef struct uiButton uiButton;

char *uiButtonText(uiButton *b);

void uiButtonSetText(uiButton *b, const char *text);

void uiButtonOnClicked(uiButton *b,
	void (*f)(uiButton *sender, void *senderData), void *data);

uiButton *uiNewButton(const char *text);

typedef struct uiBox uiBox;

void uiBoxAppend(uiBox *b, uiControl *child, int stretchy);

int uiBoxNumChildren(uiBox *b);

void uiBoxDelete(uiBox *b, int index);

int uiBoxPadded(uiBox *b);

void uiBoxSetPadded(uiBox *b, int padded);

uiBox *uiNewHorizontalBox(void);

uiBox *uiNewVerticalBox(void);

typedef struct uiCheckbox uiCheckbox;

char *uiCheckboxText(uiCheckbox *c);

void uiCheckboxSetText(uiCheckbox *c, const char *text);

void uiCheckboxOnToggled(uiCheckbox *c,
	void (*f)(uiCheckbox *sender, void *senderData), void *data);

int uiCheckboxChecked(uiCheckbox *c);

void uiCheckboxSetChecked(uiCheckbox *c, int checked);

uiCheckbox *uiNewCheckbox(const char *text);

typedef struct uiEntry uiEntry;

char *uiEntryText(uiEntry *e);

void uiEntrySetText(uiEntry *e, const char *text);

void uiEntryOnChanged(uiEntry *e,
	void (*f)(uiEntry *sender, void *senderData), void *data);

int uiEntryReadOnly(uiEntry *e);

void uiEntrySetReadOnly(uiEntry *e, int readonly);

char *uiEntryPlaceholder(uiEntry *e);

void uiEntrySetPlaceholder(uiEntry *e, const char *text);

uiEntry *uiNewEntry(void);

uiEntry *uiNewPasswordEntry(void);

uiEntry *uiNewSearchEntry(void);

typedef struct uiLabel uiLabel;

char *uiLabelText(uiLabel *l);

void uiLabelSetText(uiLabel *l, const char *text);

uiLabel *uiNewLabel(const char *text);

typedef struct uiTab uiTab;

int uiTabSelected(uiTab *t);

void uiTabSetSelected(uiTab *t, int index);

void uiTabOnSelected(uiTab *t,
        void (*f)(uiTab *sender, void *senderData), void *data);

void uiTabAppend(uiTab *t, const char *name, uiControl *c);

void uiTabInsertAt(uiTab *t, const char *name, int index, uiControl *c);

void uiTabDelete(uiTab *t, int index);

int uiTabNumPages(uiTab *t);

int uiTabMargined(uiTab *t, int index);

void uiTabSetMargined(uiTab *t, int index, int margined);

uiTab *uiNewTab(void);

typedef struct uiGroup uiGroup;

char *uiGroupTitle(uiGroup *g);

void uiGroupSetTitle(uiGroup *g, const char *title);

void uiGroupSetChild(uiGroup *g, uiControl *c);

int uiGroupMargined(uiGroup *g);

void uiGroupSetMargined(uiGroup *g, int margined);

uiGroup *uiNewGroup(const char *title);

typedef struct uiSpinbox uiSpinbox;

int uiSpinboxValue(uiSpinbox *s);

void uiSpinboxSetValue(uiSpinbox *s, int value);

void uiSpinboxOnChanged(uiSpinbox *s,
	void (*f)(uiSpinbox *sender, void *senderData), void *data);

uiSpinbox *uiNewSpinbox(int min, int max);

typedef struct uiSlider uiSlider;

int uiSliderValue(uiSlider *s);

void uiSliderSetValue(uiSlider *s, int value);

int uiSliderHasToolTip(uiSlider *s);

void uiSliderSetHasToolTip(uiSlider *s, int hasToolTip);

void uiSliderOnChanged(uiSlider *s,
	void (*f)(uiSlider *sender, void *senderData), void *data);

void uiSliderOnReleased(uiSlider *s,
	void (*f)(uiSlider *sender, void *senderData), void *data);

void uiSliderSetRange(uiSlider *s, int min, int max);

uiSlider *uiNewSlider(int min, int max);

typedef struct uiProgressBar uiProgressBar;

int uiProgressBarValue(uiProgressBar *p);

void uiProgressBarSetValue(uiProgressBar *p, int n);

uiProgressBar *uiNewProgressBar(void);

typedef struct uiSeparator uiSeparator;

uiSeparator *uiNewHorizontalSeparator(void);

uiSeparator *uiNewVerticalSeparator(void);

typedef struct uiCombobox uiCombobox;

void uiComboboxAppend(uiCombobox *c, const char *text);

void uiComboboxInsertAt(uiCombobox *c, int index, const char *text);

void uiComboboxDelete(uiCombobox *c, int index);

void uiComboboxClear(uiCombobox *c);

int uiComboboxNumItems(uiCombobox *c);

int uiComboboxSelected(uiCombobox *c);

void uiComboboxSetSelected(uiCombobox *c, int index);

void uiComboboxOnSelected(uiCombobox *c,
	void (*f)(uiCombobox *sender, void *senderData), void *data);

uiCombobox *uiNewCombobox(void);

typedef struct uiEditableCombobox uiEditableCombobox;

void uiEditableComboboxAppend(uiEditableCombobox *c, const char *text);

char *uiEditableComboboxText(uiEditableCombobox *c);

void uiEditableComboboxSetText(uiEditableCombobox *c, const char *text);

void uiEditableComboboxOnChanged(uiEditableCombobox *c,
	void (*f)(uiEditableCombobox *sender, void *senderData), void *data);

char *uiEditableComboboxPlaceholder(uiEditableCombobox *c);

void uiEditableComboboxSetPlaceholder(uiEditableCombobox *c, const char *text);

uiEditableCombobox *uiNewEditableCombobox(void);

typedef struct uiRadioButtons uiRadioButtons;

void uiRadioButtonsAppend(uiRadioButtons *r, const char *text);

int uiRadioButtonsSelected(uiRadioButtons *r);

void uiRadioButtonsSetSelected(uiRadioButtons *r, int index);

void uiRadioButtonsOnSelected(uiRadioButtons *r,
	void (*f)(uiRadioButtons *sender, void *senderData), void *data);

uiRadioButtons *uiNewRadioButtons(void);

struct tm { int tm_sec, tm_min, tm_hour, tm_mday, tm_mon, tm_year, tm_wday, tm_yday, tm_isdst; };

typedef struct uiDateTimePicker uiDateTimePicker;

void uiDateTimePickerTime(uiDateTimePicker *d, struct tm *time);

void uiDateTimePickerSetTime(uiDateTimePicker *d, const struct tm *time);

void uiDateTimePickerOnChanged(uiDateTimePicker *d,
	void (*f)(uiDateTimePicker *sender, void *senderData), void *data);

uiDateTimePicker *uiNewDateTimePicker(void);

uiDateTimePicker *uiNewDatePicker(void);

uiDateTimePicker *uiNewTimePicker(void);

typedef struct uiMultilineEntry uiMultilineEntry;

char *uiMultilineEntryText(uiMultilineEntry *e);

void uiMultilineEntrySetText(uiMultilineEntry *e, const char *text);

void uiMultilineEntryAppend(uiMultilineEntry *e, const char *text);

void uiMultilineEntryOnChanged(uiMultilineEntry *e,
	void (*f)(uiMultilineEntry *sender, void *senderData), void *data);

int uiMultilineEntryReadOnly(uiMultilineEntry *e);

void uiMultilineEntrySetReadOnly(uiMultilineEntry *e, int readonly);

uiMultilineEntry *uiNewMultilineEntry(void);

uiMultilineEntry *uiNewNonWrappingMultilineEntry(void);

typedef struct uiMenuItem uiMenuItem;

void uiMenuItemEnable(uiMenuItem *m);

void uiMenuItemDisable(uiMenuItem *m);

void uiMenuItemOnClicked(uiMenuItem *m,
	void (*f)(uiMenuItem *sender, uiWindow *window, void *senderData), void *data);

int uiMenuItemChecked(uiMenuItem *m);

void uiMenuItemSetChecked(uiMenuItem *m, int checked);

typedef struct uiMenu uiMenu;

uiMenuItem *uiMenuAppendItem(uiMenu *m, const char *name);

uiMenuItem *uiMenuAppendCheckItem(uiMenu *m, const char *name);

uiMenuItem *uiMenuAppendQuitItem(uiMenu *m);

uiMenuItem *uiMenuAppendPreferencesItem(uiMenu *m);

uiMenuItem *uiMenuAppendAboutItem(uiMenu *m);

void uiMenuAppendSeparator(uiMenu *m);

uiMenu *uiNewMenu(const char *name);

char *uiOpenFile(uiWindow *parent);

char *uiOpenFolder(uiWindow *parent);

char *uiSaveFile(uiWindow *parent);

void uiMsgBox(uiWindow *parent, const char *title, const char *description);

void uiMsgBoxError(uiWindow *parent, const char *title, const char *description);

typedef struct uiArea uiArea;
typedef struct uiAreaHandler uiAreaHandler;
typedef struct uiAreaDrawParams uiAreaDrawParams;
typedef struct uiAreaMouseEvent uiAreaMouseEvent;
typedef struct uiAreaKeyEvent uiAreaKeyEvent;

typedef struct uiDrawContext uiDrawContext;

struct uiAreaHandler {
	void (*Draw)(uiAreaHandler *, uiArea *, uiAreaDrawParams *);
	
	void (*MouseEvent)(uiAreaHandler *, uiArea *, uiAreaMouseEvent *);
	
	
	void (*MouseCrossed)(uiAreaHandler *, uiArea *, int left);
	void (*DragBroken)(uiAreaHandler *, uiArea *);
	int (*KeyEvent)(uiAreaHandler *, uiArea *, uiAreaKeyEvent *);
};

typedef unsigned int uiWindowResizeEdge; enum {
	uiWindowResizeEdgeLeft,
	uiWindowResizeEdgeTop,
	uiWindowResizeEdgeRight,
	uiWindowResizeEdgeBottom,
	uiWindowResizeEdgeTopLeft,
	uiWindowResizeEdgeTopRight,
	uiWindowResizeEdgeBottomLeft,
	uiWindowResizeEdgeBottomRight,
	
	
	
};

void uiAreaSetSize(uiArea *a, int width, int height);

void uiAreaQueueRedrawAll(uiArea *a);
void uiAreaScrollTo(uiArea *a, double x, double y, double width, double height);

void uiAreaBeginUserWindowMove(uiArea *a);
void uiAreaBeginUserWindowResize(uiArea *a, uiWindowResizeEdge edge);
uiArea *uiNewArea(uiAreaHandler *ah);
uiArea *uiNewScrollingArea(uiAreaHandler *ah, int width, int height);

struct uiAreaDrawParams {
	uiDrawContext *Context;

	
	double AreaWidth;
	double AreaHeight;

	double ClipX;
	double ClipY;
	double ClipWidth;
	double ClipHeight;
};

typedef struct uiDrawPath uiDrawPath;
typedef struct uiDrawBrush uiDrawBrush;
typedef struct uiDrawStrokeParams uiDrawStrokeParams;
typedef struct uiDrawMatrix uiDrawMatrix;

typedef struct uiDrawBrushGradientStop uiDrawBrushGradientStop;

typedef unsigned int uiDrawBrushType; enum {
	uiDrawBrushTypeSolid,
	uiDrawBrushTypeLinearGradient,
	uiDrawBrushTypeRadialGradient,
	uiDrawBrushTypeImage,
};

typedef unsigned int uiDrawLineCap; enum {
	uiDrawLineCapFlat,
	uiDrawLineCapRound,
	uiDrawLineCapSquare,
};

typedef unsigned int uiDrawLineJoin; enum {
	uiDrawLineJoinMiter,
	uiDrawLineJoinRound,
	uiDrawLineJoinBevel,
};

typedef unsigned int uiDrawFillMode; enum {
	uiDrawFillModeWinding,
	uiDrawFillModeAlternate,
};

struct uiDrawMatrix {
	double M11;
	double M12;
	double M21;
	double M22;
	double M31;
	double M32;
};

struct uiDrawBrush {
	uiDrawBrushType Type;

	
	double R;
	double G;
	double B;
	double A;

	
	double X0;		
	double Y0;		
	double X1;		
	double Y1;		
	double OuterRadius;		
	uiDrawBrushGradientStop *Stops;
	size_t NumStops;
	
	
	
	
	

	

	
};

struct uiDrawBrushGradientStop {
	double Pos;
	double R;
	double G;
	double B;
	double A;
};

struct uiDrawStrokeParams {
	uiDrawLineCap Cap;
	uiDrawLineJoin Join;
	
	double Thickness;
	double MiterLimit;
	double *Dashes;
	
	
	size_t NumDashes;
	double DashPhase;
};

uiDrawPath *uiDrawNewPath(uiDrawFillMode fillMode);
void uiDrawFreePath(uiDrawPath *p);

void uiDrawPathNewFigure(uiDrawPath *p, double x, double y);
void uiDrawPathNewFigureWithArc(uiDrawPath *p, double xCenter, double yCenter, double radius, double startAngle, double sweep, int negative);
void uiDrawPathLineTo(uiDrawPath *p, double x, double y);

void uiDrawPathArcTo(uiDrawPath *p, double xCenter, double yCenter, double radius, double startAngle, double sweep, int negative);
void uiDrawPathBezierTo(uiDrawPath *p, double c1x, double c1y, double c2x, double c2y, double endX, double endY);

void uiDrawPathCloseFigure(uiDrawPath *p);

void uiDrawPathAddRectangle(uiDrawPath *p, double x, double y, double width, double height);

int uiDrawPathEnded(uiDrawPath *p);
void uiDrawPathEnd(uiDrawPath *p);

void uiDrawStroke(uiDrawContext *c, uiDrawPath *path, uiDrawBrush *b, uiDrawStrokeParams *p);
void uiDrawFill(uiDrawContext *c, uiDrawPath *path, uiDrawBrush *b);

void uiDrawMatrixSetIdentity(uiDrawMatrix *m);
void uiDrawMatrixTranslate(uiDrawMatrix *m, double x, double y);
void uiDrawMatrixScale(uiDrawMatrix *m, double xCenter, double yCenter, double x, double y);
void uiDrawMatrixRotate(uiDrawMatrix *m, double x, double y, double amount);
void uiDrawMatrixSkew(uiDrawMatrix *m, double x, double y, double xamount, double yamount);
void uiDrawMatrixMultiply(uiDrawMatrix *dest, uiDrawMatrix *src);
int uiDrawMatrixInvertible(uiDrawMatrix *m);
int uiDrawMatrixInvert(uiDrawMatrix *m);
void uiDrawMatrixTransformPoint(uiDrawMatrix *m, double *x, double *y);
void uiDrawMatrixTransformSize(uiDrawMatrix *m, double *x, double *y);

void uiDrawTransform(uiDrawContext *c, uiDrawMatrix *m);

void uiDrawClip(uiDrawContext *c, uiDrawPath *path);

void uiDrawSave(uiDrawContext *c);
void uiDrawRestore(uiDrawContext *c);

typedef struct uiAttribute uiAttribute;

void uiFreeAttribute(uiAttribute *a);

typedef unsigned int uiAttributeType; enum {
	uiAttributeTypeFamily,
	uiAttributeTypeSize,
	uiAttributeTypeWeight,
	uiAttributeTypeItalic,
	uiAttributeTypeStretch,
	uiAttributeTypeColor,
	uiAttributeTypeBackground,
	uiAttributeTypeUnderline,
	uiAttributeTypeUnderlineColor,
	uiAttributeTypeFeatures,
};

uiAttributeType uiAttributeGetType(const uiAttribute *a);

uiAttribute *uiNewFamilyAttribute(const char *family);

const char *uiAttributeFamily(const uiAttribute *a);

uiAttribute *uiNewSizeAttribute(double size);

double uiAttributeSize(const uiAttribute *a);

typedef unsigned int uiTextWeight; enum {
	uiTextWeightMinimum = 0,
	uiTextWeightThin = 100,
	uiTextWeightUltraLight = 200,
	uiTextWeightLight = 300,
	uiTextWeightBook = 350,
	uiTextWeightNormal = 400,
	uiTextWeightMedium = 500,
	uiTextWeightSemiBold = 600,
	uiTextWeightBold = 700,
	uiTextWeightUltraBold = 800,
	uiTextWeightHeavy = 900,
	uiTextWeightUltraHeavy = 950,
	uiTextWeightMaximum = 1000,
};

uiAttribute *uiNewWeightAttribute(uiTextWeight weight);

uiTextWeight uiAttributeWeight(const uiAttribute *a);

typedef unsigned int uiTextItalic; enum {
	uiTextItalicNormal,
	uiTextItalicOblique,
	uiTextItalicItalic,
};

uiAttribute *uiNewItalicAttribute(uiTextItalic italic);

uiTextItalic uiAttributeItalic(const uiAttribute *a);

typedef unsigned int uiTextStretch; enum {
	uiTextStretchUltraCondensed,
	uiTextStretchExtraCondensed,
	uiTextStretchCondensed,
	uiTextStretchSemiCondensed,
	uiTextStretchNormal,
	uiTextStretchSemiExpanded,
	uiTextStretchExpanded,
	uiTextStretchExtraExpanded,
	uiTextStretchUltraExpanded,
};

uiAttribute *uiNewStretchAttribute(uiTextStretch stretch);

uiTextStretch uiAttributeStretch(const uiAttribute *a);

uiAttribute *uiNewColorAttribute(double r, double g, double b, double a);

void uiAttributeColor(const uiAttribute *a, double *r, double *g, double *b, double *alpha);

uiAttribute *uiNewBackgroundAttribute(double r, double g, double b, double a);

typedef unsigned int uiUnderline; enum {
	uiUnderlineNone,
	uiUnderlineSingle,
	uiUnderlineDouble,
	uiUnderlineSuggestion,		
};

uiAttribute *uiNewUnderlineAttribute(uiUnderline u);

uiUnderline uiAttributeUnderline(const uiAttribute *a);

typedef unsigned int uiUnderlineColor; enum {
	uiUnderlineColorCustom,
	uiUnderlineColorSpelling,
	uiUnderlineColorGrammar,
	uiUnderlineColorAuxiliary,		
};

uiAttribute *uiNewUnderlineColorAttribute(uiUnderlineColor u, double r, double g, double b, double a);

void uiAttributeUnderlineColor(const uiAttribute *a, uiUnderlineColor *u, double *r, double *g, double *b, double *alpha);

typedef struct uiOpenTypeFeatures uiOpenTypeFeatures;

typedef uiForEach (*uiOpenTypeFeaturesForEachFunc)(const uiOpenTypeFeatures *otf, char a, char b, char c, char d, uint32_t value, void *data);

uiOpenTypeFeatures *uiNewOpenTypeFeatures(void);

void uiFreeOpenTypeFeatures(uiOpenTypeFeatures *otf);

uiOpenTypeFeatures *uiOpenTypeFeaturesClone(const uiOpenTypeFeatures *otf);

void uiOpenTypeFeaturesAdd(uiOpenTypeFeatures *otf, char a, char b, char c, char d, uint32_t value);

void uiOpenTypeFeaturesRemove(uiOpenTypeFeatures *otf, char a, char b, char c, char d);

int uiOpenTypeFeaturesGet(const uiOpenTypeFeatures *otf, char a, char b, char c, char d, uint32_t *value);

void uiOpenTypeFeaturesForEach(const uiOpenTypeFeatures *otf, uiOpenTypeFeaturesForEachFunc f, void *data);

uiAttribute *uiNewFeaturesAttribute(const uiOpenTypeFeatures *otf);

const uiOpenTypeFeatures *uiAttributeFeatures(const uiAttribute *a);

typedef struct uiAttributedString uiAttributedString;

typedef uiForEach (*uiAttributedStringForEachAttributeFunc)(const uiAttributedString *s, const uiAttribute *a, size_t start, size_t end, void *data);

uiAttributedString *uiNewAttributedString(const char *initialString);

void uiFreeAttributedString(uiAttributedString *s);

const char *uiAttributedStringString(const uiAttributedString *s);

size_t uiAttributedStringLen(const uiAttributedString *s);

void uiAttributedStringAppendUnattributed(uiAttributedString *s, const char *str);

void uiAttributedStringInsertAtUnattributed(uiAttributedString *s, const char *str, size_t at);

void uiAttributedStringDelete(uiAttributedString *s, size_t start, size_t end);

void uiAttributedStringSetAttribute(uiAttributedString *s, uiAttribute *a, size_t start, size_t end);

void uiAttributedStringForEachAttribute(const uiAttributedString *s, uiAttributedStringForEachAttributeFunc f, void *data);

size_t uiAttributedStringNumGraphemes(uiAttributedString *s);

size_t uiAttributedStringByteIndexToGrapheme(uiAttributedString *s, size_t pos);

size_t uiAttributedStringGraphemeToByteIndex(uiAttributedString *s, size_t pos);

typedef struct uiFontDescriptor uiFontDescriptor;

struct uiFontDescriptor {
	
	char *Family;
	double Size;
	uiTextWeight Weight;
	uiTextItalic Italic;
	uiTextStretch Stretch;
};

void uiLoadControlFont(uiFontDescriptor *f);
void uiFreeFontDescriptor(uiFontDescriptor *desc);

typedef struct uiDrawTextLayout uiDrawTextLayout;

typedef unsigned int uiDrawTextAlign; enum {
	uiDrawTextAlignLeft,
	uiDrawTextAlignCenter,
	uiDrawTextAlignRight,
};

typedef struct uiDrawTextLayoutParams uiDrawTextLayoutParams;

struct uiDrawTextLayoutParams {
	uiAttributedString *String;
	uiFontDescriptor *DefaultFont;
	double Width;
	uiDrawTextAlign Align;
};

uiDrawTextLayout *uiDrawNewTextLayout(uiDrawTextLayoutParams *params);

void uiDrawFreeTextLayout(uiDrawTextLayout *tl);

void uiDrawText(uiDrawContext *c, uiDrawTextLayout *tl, double x, double y);

void uiDrawTextLayoutExtents(uiDrawTextLayout *tl, double *width, double *height);

typedef struct uiFontButton uiFontButton;

void uiFontButtonFont(uiFontButton *b, uiFontDescriptor *desc);

void uiFontButtonOnChanged(uiFontButton *b,
	void (*f)(uiFontButton *sender, void *senderData), void *data);

uiFontButton *uiNewFontButton(void);

void uiFreeFontButtonFont(uiFontDescriptor *desc);

typedef unsigned int uiModifiers; enum {
	uiModifierCtrl  = 1 << 0, 
	uiModifierAlt   = 1 << 1, 
	uiModifierShift = 1 << 2, 
	uiModifierSuper = 1 << 3, 
};

struct uiAreaMouseEvent {
	
	double X;
	double Y;

	
	double AreaWidth;
	double AreaHeight;

	int Down;
	int Up;

	int Count;

	uiModifiers Modifiers;

	uint64_t Held1To64;
};

typedef unsigned int uiExtKey; enum {
	uiExtKeyEscape = 1,
	uiExtKeyInsert,			
	uiExtKeyDelete,
	uiExtKeyHome,
	uiExtKeyEnd,
	uiExtKeyPageUp,
	uiExtKeyPageDown,
	uiExtKeyUp,
	uiExtKeyDown,
	uiExtKeyLeft,
	uiExtKeyRight,
	uiExtKeyF1,			
	uiExtKeyF2,
	uiExtKeyF3,
	uiExtKeyF4,
	uiExtKeyF5,
	uiExtKeyF6,
	uiExtKeyF7,
	uiExtKeyF8,
	uiExtKeyF9,
	uiExtKeyF10,
	uiExtKeyF11,
	uiExtKeyF12,
	uiExtKeyN0,			
	uiExtKeyN1,			
	uiExtKeyN2,
	uiExtKeyN3,
	uiExtKeyN4,
	uiExtKeyN5,
	uiExtKeyN6,
	uiExtKeyN7,
	uiExtKeyN8,
	uiExtKeyN9,
	uiExtKeyNDot,
	uiExtKeyNEnter,
	uiExtKeyNAdd,
	uiExtKeyNSubtract,
	uiExtKeyNMultiply,
	uiExtKeyNDivide,
};

struct uiAreaKeyEvent {
	char Key;
	uiExtKey ExtKey;
	uiModifiers Modifier;

	uiModifiers Modifiers;

	int Up;
};

typedef struct uiColorButton uiColorButton;

void uiColorButtonColor(uiColorButton *b, double *r, double *g, double *bl, double *a);

void uiColorButtonSetColor(uiColorButton *b, double r, double g, double bl, double a);

void uiColorButtonOnChanged(uiColorButton *b,
	void (*f)(uiColorButton *sender, void *senderData), void *data);

uiColorButton *uiNewColorButton(void);

typedef struct uiForm uiForm;

void uiFormAppend(uiForm *f, const char *label, uiControl *c, int stretchy);

int uiFormNumChildren(uiForm *f);

void uiFormDelete(uiForm *f, int index);

int uiFormPadded(uiForm *f);

void uiFormSetPadded(uiForm *f, int padded);

uiForm *uiNewForm(void);

typedef unsigned int uiAlign; enum {
	uiAlignFill,	
	uiAlignStart,	
	uiAlignCenter,	
	uiAlignEnd,	
};

typedef unsigned int uiAt; enum {
	uiAtLeading,	
	uiAtTop,	
	uiAtTrailing,	
	uiAtBottom,	
};

typedef struct uiGrid uiGrid;

void uiGridAppend(uiGrid *g, uiControl *c, int left, int top, int xspan, int yspan, int hexpand, uiAlign halign, int vexpand, uiAlign valign);

void uiGridInsertAt(uiGrid *g, uiControl *c, uiControl *existing, uiAt at, int xspan, int yspan, int hexpand, uiAlign halign, int vexpand, uiAlign valign);

int uiGridPadded(uiGrid *g);

void uiGridSetPadded(uiGrid *g, int padded);

uiGrid *uiNewGrid(void);

typedef struct uiImage uiImage;

uiImage *uiNewImage(double width, double height);

void uiFreeImage(uiImage *i);

void uiImageAppend(uiImage *i, void *pixels, int pixelWidth, int pixelHeight, int byteStride);

typedef struct uiTableValue uiTableValue;

void uiFreeTableValue(uiTableValue *v);

typedef unsigned int uiTableValueType; enum {
	uiTableValueTypeString,
	uiTableValueTypeImage,
	uiTableValueTypeInt,
	uiTableValueTypeColor,
};

uiTableValueType uiTableValueGetType(const uiTableValue *v);

uiTableValue *uiNewTableValueString(const char *str);

const char *uiTableValueString(const uiTableValue *v);

uiTableValue *uiNewTableValueImage(uiImage *img);

uiImage *uiTableValueImage(const uiTableValue *v);

uiTableValue *uiNewTableValueInt(int i);

int uiTableValueInt(const uiTableValue *v);

uiTableValue *uiNewTableValueColor(double r, double g, double b, double a);

void uiTableValueColor(const uiTableValue *v, double *r, double *g, double *b, double *a);

typedef unsigned int uiSortIndicator; enum {
	uiSortIndicatorNone,
	uiSortIndicatorAscending,
	uiSortIndicatorDescending
};

typedef struct uiTableModel uiTableModel;

typedef struct uiTableModelHandler uiTableModelHandler;
struct uiTableModelHandler {
	
	int (*NumColumns)(uiTableModelHandler *, uiTableModel *);

	
	uiTableValueType (*ColumnType)(uiTableModelHandler *, uiTableModel *, int column);

	
	int (*NumRows)(uiTableModelHandler *, uiTableModel *);

	
	uiTableValue *(*CellValue)(uiTableModelHandler *mh, uiTableModel *m, int row, int column);

	
	void (*SetCellValue)(uiTableModelHandler *, uiTableModel *, int, int, const uiTableValue *);
};

uiTableModel *uiNewTableModel(uiTableModelHandler *mh);

void uiFreeTableModel(uiTableModel *m);

void uiTableModelRowInserted(uiTableModel *m, int newIndex);

void uiTableModelRowChanged(uiTableModel *m, int index);

void uiTableModelRowDeleted(uiTableModel *m, int oldIndex);

typedef struct uiTableTextColumnOptionalParams uiTableTextColumnOptionalParams;
struct uiTableTextColumnOptionalParams {
	
	int ColorModelColumn;
};

typedef struct uiTableParams uiTableParams;
struct uiTableParams {
	
	uiTableModel *Model;
	
	int RowBackgroundColorModelColumn;
};

typedef struct uiTable uiTable;

void uiTableAppendTextColumn(uiTable *t,
	const char *name,
	int textModelColumn,
	int textEditableModelColumn,
	uiTableTextColumnOptionalParams *textParams);

void uiTableAppendImageColumn(uiTable *t,
	const char *name,
	int imageModelColumn);

void uiTableAppendImageTextColumn(uiTable *t,
	const char *name,
	int imageModelColumn,
	int textModelColumn,
	int textEditableModelColumn,
	uiTableTextColumnOptionalParams *textParams);

void uiTableAppendCheckboxColumn(uiTable *t,
	const char *name,
	int checkboxModelColumn,
	int checkboxEditableModelColumn);

void uiTableAppendCheckboxTextColumn(uiTable *t,
	const char *name,
	int checkboxModelColumn,
	int checkboxEditableModelColumn,
	int textModelColumn,
	int textEditableModelColumn,
	uiTableTextColumnOptionalParams *textParams);

void uiTableAppendProgressBarColumn(uiTable *t,
	const char *name,
	int progressModelColumn);

void uiTableAppendButtonColumn(uiTable *t,
	const char *name,
	int buttonModelColumn,
	int buttonClickableModelColumn);

int uiTableHeaderVisible(uiTable *t);

void uiTableHeaderSetVisible(uiTable *t, int visible);

uiTable *uiNewTable(uiTableParams *params);

void uiTableOnRowClicked(uiTable *t,
	void (*f)(uiTable *t, int row, void *data),
	void *data);

void uiTableOnRowDoubleClicked(uiTable *t,
	void (*f)(uiTable *t, int row, void *data),
	void *data);

void uiTableHeaderSetSortIndicator(uiTable *t,
	int column,
	uiSortIndicator indicator);

uiSortIndicator uiTableHeaderSortIndicator(uiTable *t, int column);

void uiTableHeaderOnClicked(uiTable *t,
	void (*f)(uiTable *sender, int column, void *senderData), void *data);

int uiTableColumnWidth(uiTable *t, int column);

void uiTableColumnSetWidth(uiTable *t, int column, int width);

typedef unsigned int uiTableSelectionMode; enum {
	
        uiTableSelectionModeNone,
        uiTableSelectionModeZeroOrOne,  
        uiTableSelectionModeOne,        
        uiTableSelectionModeZeroOrMany, 
};

uiTableSelectionMode uiTableGetSelectionMode(uiTable *t);

void uiTableSetSelectionMode(uiTable *t, uiTableSelectionMode mode);

void uiTableOnSelectionChanged(uiTable *t, void (*f)(uiTable *t, void *data), void *data);

typedef struct uiTableSelection uiTableSelection;
struct uiTableSelection
{
	int NumRows; 
	int *Rows;   
};

uiTableSelection* uiTableGetSelection(uiTable *t);

void uiTableSetSelection(uiTable *t, uiTableSelection *sel);

void uiFreeTableSelection(uiTableSelection* s);

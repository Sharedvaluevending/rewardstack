<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    qrCodes: Array,
    layouts: Object,
    paperSizes: Object,
    templates: Array,
    fonts: Object,
});

const fonts = computed(() => props.fonts || {});

const FONT_IMPORT_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Poppins:wght@400;600;700&family=Playfair+Display:wght@400;600;700&family=Merriweather:wght@400;700&family=Oswald:wght@400;600;700&family=Bebas+Neue&family=Pacifico&family=Dancing+Script:wght@400;600;700&family=Permanent+Marker&family=Indie+Flower&family=Amatic+SC:wght@400;700&family=Fredoka+One&display=swap';
const FONT_LINK_ID = 'print-studio-fonts';
let fontsPreloaded = false;

const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta?.content) return meta.content;

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (match?.[1]) {
        try {
            return decodeURIComponent(match[1]);
        } catch (_) {
            return match[1];
        }
    }
    return '';
};

const refreshCsrfToken = async () => {
    try {
        const res = await fetch(window.location.href, { credentials: 'same-origin' });
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const meta = doc.querySelector('meta[name="csrf-token"]');
        if (meta?.content) {
            let current = document.querySelector('meta[name="csrf-token"]');
            if (!current) {
                current = document.createElement('meta');
                current.setAttribute('name', 'csrf-token');
                document.head.appendChild(current);
            }
            current.setAttribute('content', meta.content);
            return meta.content;
        }
    } catch (_) {
        // ignore
    }
    return '';
};

const ensureFontsLink = () => {
    if (document.getElementById(FONT_LINK_ID)) return;

    const preconnect1 = document.createElement('link');
    preconnect1.rel = 'preconnect';
    preconnect1.href = 'https://fonts.googleapis.com';
    document.head.appendChild(preconnect1);

    const preconnect2 = document.createElement('link');
    preconnect2.rel = 'preconnect';
    preconnect2.href = 'https://fonts.gstatic.com';
    preconnect2.crossOrigin = 'anonymous';
    document.head.appendChild(preconnect2);

    const link = document.createElement('link');
    link.id = FONT_LINK_ID;
    link.rel = 'stylesheet';
    link.href = FONT_IMPORT_URL;
    document.head.appendChild(link);
};

const loadFontFamily = async (fontName) => {
    if (!fontName || !document.fonts) return;
    try {
        await document.fonts.load(`16px "${fontName}"`);
    } catch (_) {
        // ignore font load errors; fallback fonts will apply
    }
};

const preloadAllFonts = async () => {
    if (fontsPreloaded) return;
    ensureFontsLink();
    const fontNames = Object.keys(props.fonts || {});
    if (document.fonts && fontNames.length > 0) {
        await Promise.allSettled(fontNames.map(loadFontFamily));
    }
    fontsPreloaded = true;
};

const selectedQRCodes = ref([]);
const layout = ref('2x2');
const paperSize = ref('letter');
const qrSize = ref(150);
const spacing = ref(10);
const includeText = ref(true);
const includeBorder = ref(false);
const quantity = ref(1);
const generating = ref(false);
const previewData = ref(null);

const headerFooterOptions = [
    'SCAN • PLAY • WIN',
    'SCAN TO WIN DEALS',
    'WAITING? SCAN & WIN',
    'SCAN FOR INSTANT DEALS',
];

const headerText = ref('');
const footerText = ref('');
const headerFooterFont = ref(Object.keys(props.fonts || {})[0] || 'Inter');
const headerFooterColor = ref('#111111');
const headerFontSize = ref(16);
const footerFontSize = ref(16);
const headerFooterOutline = ref(false);
const headerFooterOutlineColor = ref('#ffffff');

const headerFooterFontStack = computed(() => {
    const font = String(headerFooterFont.value || 'Inter').replace(/"/g, '');
    return `"${font}", Arial, sans-serif`;
});

const headerTextShadow = computed(() => {
    if (!headerFooterOutline.value) return 'none';
    const c = headerFooterOutlineColor.value || '#ffffff';
    return `-1px -1px 0 ${c}, 1px -1px 0 ${c}, -1px 1px 0 ${c}, 1px 1px 0 ${c}`;
});

const headerStyle = computed(() => ({
    fontFamily: headerFooterFontStack.value,
    color: headerFooterColor.value,
    fontSize: `${headerFontSize.value}px`,
    textShadow: headerTextShadow.value,
}));

const footerStyle = computed(() => ({
    fontFamily: headerFooterFontStack.value,
    color: headerFooterColor.value,
    fontSize: `${footerFontSize.value}px`,
    textShadow: headerTextShadow.value,
}));

// Print sizing helpers (the print renderer assumes 300 DPI for layout)
const PRINT_DPI = 300;
const PRINT_MARGIN_IN = 0.25;
const LABEL_HEIGHT_IN = 0.20;
const PREVIEW_PX_PER_IN = 32; // visual-only scale for the on-screen preview
const PREVIEW_PAPER_PADDING_PX = 16; // Tailwind p-4
const PREVIEW_LABEL_PX = 12; // reserved space under QR for label in preview
const qrSizeIn = computed(() => (Number(qrSize.value || 0) / PRINT_DPI));
const qrSizeMm = computed(() => qrSizeIn.value * 25.4);

const setQrSizeInches = (inches) => {
    const px = Math.round(Number(inches) * PRINT_DPI);
    // Keep within server limits (50..1200)
    qrSize.value = Math.min(1200, Math.max(50, px));
};

/**
 * Build a print-only HTML document from the server-generated sheet data.
 * This avoids printing the whole Inertia page (nav, sidebar, etc.).
 */
const buildPrintHtml = (sheets) => {
    const esc = (s) =>
        String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

    const pages = (Array.isArray(sheets) ? sheets : []).map((sheet, pageIndex) => {
        const paperW = Number(sheet?.paper?.width ?? 8.5);
        const paperH = Number(sheet?.paper?.height ?? 11);
        const cols = Number(sheet?.layout?.columns ?? currentLayout.value.columns);
        const rows = Number(sheet?.layout?.rows ?? currentLayout.value.rows);

        // Use requested sizes but scale down if they won't fit on the paper.
        const marginIn = 0.5;
        const availW = Math.max(0.1, paperW - marginIn * 2);
        const availH = Math.max(0.1, paperH - marginIn * 2);

        const requestedQrPx = Number(sheet?.options?.qr_size ?? qrSize.value);
        const requestedGapPx = Number(sheet?.options?.spacing ?? spacing.value);

        // Convert "px-ish" inputs to inches for paper layout heuristics (assume 300dpi for print layout)
        const dpi = 300;
        const qrIn = requestedQrPx / dpi;
        const gapIn = requestedGapPx / dpi;
        const labelIn = (sheet?.options?.include_text ?? includeText.value) ? 0.20 : 0; // ~0.2" for label

        const neededW = cols * qrIn + Math.max(0, cols - 1) * gapIn;
        const neededH = rows * (qrIn + labelIn) + Math.max(0, rows - 1) * gapIn;

        const scale = Math.min(1, availW / neededW, availH / neededH);
        const qrInScaled = qrIn * scale;
        const gapInScaled = gapIn * scale;

        const qrCodes = Array.isArray(sheet?.qr_codes) ? sheet.qr_codes : [];

        const cells = [];
        for (let i = 0; i < rows * cols; i++) {
            const qr = qrCodes[i] || null;
            if (!qr) {
                cells.push(`<div class="cell empty"></div>`);
                continue;
            }

            const imgSrc = qr.image || '';
            const showName = !!(sheet?.options?.include_text ?? includeText.value);
            const showBorder = !!(sheet?.options?.include_border ?? includeBorder.value);

            cells.push(`
                <div class="cell">
                    <div class="qrWrap ${showBorder ? 'cut' : ''}">
                        <img class="qrImg" src="${imgSrc}" alt="${esc(qr.name)}" />
                    </div>
                    ${showName ? `<div class="qrName">${esc(qr.name)}</div>` : ``}
                </div>
            `);
        }

        const headerText = sheet?.options?.header_text || '';
        const footerText = sheet?.options?.footer_text || '';
        const headerFooterFont = sheet?.options?.header_footer_font || 'Inter';
        const headerFooterColor = sheet?.options?.header_footer_color || '#111111';
        const headerFontSize = Number(sheet?.options?.header_font_size ?? 16);
        const footerFontSize = Number(sheet?.options?.footer_font_size ?? 16);
        const headerFooterOutline = !!sheet?.options?.header_footer_outline;
        const headerFooterOutlineColor = sheet?.options?.header_footer_outline_color || '#ffffff';

        const outlineShadow = headerFooterOutline
            ? `text-shadow: -1px -1px 0 ${headerFooterOutlineColor}, 1px -1px 0 ${headerFooterOutlineColor}, -1px 1px 0 ${headerFooterOutlineColor}, 1px 1px 0 ${headerFooterOutlineColor};`
            : '';

        return `
            <section class="page" style="--paper-w:${paperW}in; --paper-h:${paperH}in; --cols:${cols}; --rows:${rows}; --qr:${qrInScaled}in; --gap:${gapInScaled}in; --margin:${marginIn}in;">
                ${headerText ? `<div class="pageHeader" style="font-family:'${esc(headerFooterFont)}', Arial, sans-serif; color:${esc(headerFooterColor)}; font-size:${headerFontSize}pt; ${outlineShadow}">${esc(headerText)}</div>` : ``}
                <div class="sheet">
                    ${cells.join('')}
                </div>
                ${footerText ? `<div class="pageFooter" style="font-family:'${esc(headerFooterFont)}', Arial, sans-serif; color:${esc(headerFooterColor)}; font-size:${footerFontSize}pt; ${outlineShadow}">${esc(footerText)}</div>` : ``}
                <div class="pageNum">Page ${pageIndex + 1} of ${sheets.length}</div>
            </section>
        `;
    });

    return `<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>QR Print Sheet</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="${FONT_IMPORT_URL}">
  <style>
    html, body { margin: 0; padding: 0; background: #fff; color: #111; }
    * { box-sizing: border-box; }

    .page {
      width: var(--paper-w);
      height: var(--paper-h);
      margin: 0 auto;
      background: #fff;
      position: relative;
      page-break-after: always;
      break-after: page;
      padding: var(--margin);
    }
    .sheet {
      width: 100%;
      height: calc(100% - 14px);
      display: grid;
      grid-template-columns: repeat(var(--cols), 1fr);
      grid-template-rows: repeat(var(--rows), 1fr);
      gap: var(--gap);
      align-items: center;
      justify-items: center;
    }
    .cell {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .qrWrap {
      width: var(--qr);
      height: var(--qr);
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fff;
    }
    .qrWrap.cut { border: 2px dashed rgba(0,0,0,0.35); }
    .qrImg { width: 100%; height: 100%; object-fit: contain; }
    .qrName { margin-top: 6px; font: 12px/1.2 Arial, sans-serif; text-align: center; max-width: 100%; word-break: break-word; }
    .empty { }
    .pageNum { position: absolute; bottom: 6px; right: 10px; font: 10px Arial, sans-serif; color: #666; }
    .pageHeader,
    .pageFooter {
      position: absolute;
      left: var(--margin);
      right: var(--margin);
      text-align: center;
      font-size: 16pt;
      line-height: 1.2;
    }
    .pageHeader { top: 0.18in; }
    .pageFooter { bottom: 0.18in; }

    @media print {
      @page { size: auto; margin: 0; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .page { margin: 0; }
    }
  </style>
</head>
<body>
  ${pages.join('')}
  <scr` + `ipt>
    // Wait for document and fonts to load so header/footer fonts render correctly.
    window.addEventListener('load', () => {
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => window.print());
      } else {
        setTimeout(() => window.print(), 500);
      }
    });
  </scr` + `ipt>
</body>
</html>`;
};

const writeToPrintWindow = (w, sheets) => {
    const html = buildPrintHtml(sheets);
    w.document.open();
    w.document.write(html);
    w.document.close();
};

const closePrintWindow = (w) => {
    if (w && !w.closed) {
        try {
            w.close();
        } catch (_) {
            // ignore
        }
    }
};

// No-popup fallback print (prints from a hidden iframe)
const printViaIframe = (sheets) => {
    const html = buildPrintHtml(sheets);
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.onload = () => {
        try {
            iframe.contentWindow?.focus();
            iframe.contentWindow?.print();
        } finally {
            // Cleanup
            setTimeout(() => iframe.remove(), 2000);
        }
    };
    // srcdoc is same-origin; avoids popup blockers.
    iframe.srcdoc = html;
    document.body.appendChild(iframe);
};

const downloadSheetsAsPng = async (sheets) => {
    await preloadAllFonts();
    // Ensure fonts are loaded so canvas can use header/footer font (canvas uses document fonts).
    if (document.fonts && document.fonts.ready) {
        await document.fonts.ready;
    }
    // Render each sheet to a PNG using canvas and the embedded base64 images.
    const dpi = 300;
    const marginIn = 0.5;
    const textInsetIn = 0.18;
    const toDataUrl = (blob) =>
        new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });

    const downloadBlob = (blob, filename) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    for (let pageIndex = 0; pageIndex < sheets.length; pageIndex++) {
        const sheet = sheets[pageIndex];
        const paperW = Number(sheet?.paper?.width ?? 8.5);
        const paperH = Number(sheet?.paper?.height ?? 11);
        const cols = Number(sheet?.layout?.columns ?? currentLayout.value.columns);
        const rows = Number(sheet?.layout?.rows ?? currentLayout.value.rows);
        const showName = !!(sheet?.options?.include_text ?? includeText.value);
        const showBorder = !!(sheet?.options?.include_border ?? includeBorder.value);

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(paperW * dpi);
        canvas.height = Math.round(paperH * dpi);
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Canvas not supported');

        // White background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const marginPx = Math.round(marginIn * dpi);
        const availW = canvas.width - marginPx * 2;
        const availH = canvas.height - marginPx * 2;

        const requestedQrPx = Number(sheet?.options?.qr_size ?? qrSize.value);
        const requestedGapPx = Number(sheet?.options?.spacing ?? spacing.value);
        const labelPx = showName ? Math.round(0.20 * dpi) : 0;

        const neededW = cols * requestedQrPx + Math.max(0, cols - 1) * requestedGapPx;
        const neededH = rows * (requestedQrPx + labelPx) + Math.max(0, rows - 1) * requestedGapPx;
        const scale = Math.min(1, availW / neededW, availH / neededH);

        const qrPx = Math.floor(requestedQrPx * scale);
        const gapPx = Math.floor(requestedGapPx * scale);

        // Compute start offsets so grid is centered in available area
        const gridW = cols * qrPx + Math.max(0, cols - 1) * gapPx;
        const gridH = rows * (qrPx + labelPx) + Math.max(0, rows - 1) * gapPx;
        const startX = marginPx + Math.floor((availW - gridW) / 2);
        const startY = marginPx + Math.floor((availH - gridH) / 2);

        const qrCodes = Array.isArray(sheet?.qr_codes) ? sheet.qr_codes : [];
        const headerText = sheet?.options?.header_text || '';
        const footerText = sheet?.options?.footer_text || '';
        const headerFooterFont = sheet?.options?.header_footer_font || 'Inter';
        const headerFooterColor = sheet?.options?.header_footer_color || '#111111';
        const headerFontPt = Number(sheet?.options?.header_font_size ?? 16);
        const footerFontPt = Number(sheet?.options?.footer_font_size ?? 16);
        const headerFooterOutline = !!sheet?.options?.header_footer_outline;
        const headerFooterOutlineColor = sheet?.options?.header_footer_outline_color || '#ffffff';

        // Preload images
        const loaded = await Promise.all(
            qrCodes.map((qr) => {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => resolve({ qr, img });
                    img.onerror = () => resolve({ qr, img: null });
                    img.src = qr.image;
                });
            })
        );

        ctx.fillStyle = '#000000';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.font = `${Math.max(10, Math.floor(12 * scale))}px Arial`;

        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const i = r * cols + c;
                const x = startX + c * (qrPx + gapPx);
                const y = startY + r * (qrPx + labelPx + gapPx);

                const item = loaded[i];
                if (!item?.img) continue;

                // Optional cut border
                if (showBorder) {
                    ctx.strokeStyle = 'rgba(0,0,0,0.35)';
                    ctx.setLineDash([8, 6]);
                    ctx.lineWidth = 2;
                    ctx.strokeRect(x, y, qrPx, qrPx);
                    ctx.setLineDash([]);
                }

                // Draw QR
                ctx.drawImage(item.img, x, y, qrPx, qrPx);

                // Draw label
                if (showName) {
                    const name = String(item.qr?.name ?? '');
                    ctx.fillStyle = '#000';
                    ctx.fillText(name, x + qrPx / 2, y + qrPx + 6);
                }
            }
        }

        // Header/Footer text
        if (headerText || footerText) {
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            if (headerText) {
                const headerFontPx = Math.round((headerFontPt / 72) * dpi);
                ctx.font = `${headerFontPx}px "${headerFooterFont}", Arial, sans-serif`;
                const x = canvas.width / 2;
                const y = Math.round(textInsetIn * dpi);
                if (headerFooterOutline) {
                    ctx.strokeStyle = headerFooterOutlineColor;
                    ctx.lineWidth = Math.max(1, Math.round(headerFontPx * 0.08));
                    ctx.strokeText(headerText, x, y);
                }
                ctx.fillStyle = headerFooterColor;
                ctx.fillText(headerText, x, y);
            }
            if (footerText) {
                const footerFontPx = Math.round((footerFontPt / 72) * dpi);
                ctx.font = `${footerFontPx}px "${headerFooterFont}", Arial, sans-serif`;
                const x = canvas.width / 2;
                const y = Math.round(canvas.height - textInsetIn * dpi);
                if (headerFooterOutline) {
                    ctx.strokeStyle = headerFooterOutlineColor;
                    ctx.lineWidth = Math.max(1, Math.round(footerFontPx * 0.08));
                    ctx.strokeText(footerText, x, y);
                }
                ctx.fillStyle = headerFooterColor;
                ctx.fillText(footerText, x, y);
            }
        }

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Failed to create PNG');

        // Download each sheet separately
        downloadBlob(blob, `qr-sheet-${pageIndex + 1}.png`);
    }
};

// Filter out merch_referral QR codes - must order merch to get those
const printableQrCodes = computed(() => {
    return (props.qrCodes || []).filter(qr => qr.type !== 'merch_referral');
});

// Toggle QR code selection
const toggleQRCode = (id) => {
    const index = selectedQRCodes.value.indexOf(id);
    if (index > -1) {
        selectedQRCodes.value.splice(index, 1);
    } else {
        selectedQRCodes.value.push(id);
    }
};

// Select all QR codes (only printable ones)
const selectAll = () => {
    selectedQRCodes.value = printableQrCodes.value.map(qr => qr.id);
};

// Clear selection
const clearSelection = () => {
    selectedQRCodes.value = [];
};

// Get QR code image URL
const getQRImageUrl = (qr) => {
    if (qr.design?.generated_path) {
        return `/storage/${qr.design.generated_path}`;
    }
    if (qr.image_url) {
        return qr.image_url;
    }
    return null;
};

// Apply template
const applyTemplate = (template) => {
    layout.value = template.layout;
    paperSize.value = template.paper_size;
};

const requestedLayout = computed(() => {
    return props.layouts?.[layout.value] || props.layouts?.['2x2'] || { columns: 2, rows: 2, name: '4 per page' };
});

const currentPaper = computed(() => {
    return props.paperSizes?.[paperSize.value] || { width: 8.5, height: 11, name: 'Letter (8.5" x 11")' };
});

const maxLayoutThatFits = computed(() => {
    const paperW = Number(currentPaper.value?.width ?? 8.5);
    const paperH = Number(currentPaper.value?.height ?? 11);

    const availW = Math.max(0.1, paperW - PRINT_MARGIN_IN * 2);
    const availH = Math.max(0.1, paperH - PRINT_MARGIN_IN * 2);

    const qrIn = Math.max(0.01, Number(qrSize.value || 0) / PRINT_DPI);
    const gapIn = Math.max(0, Number(spacing.value || 0) / PRINT_DPI);
    const labelIn = includeText.value ? LABEL_HEIGHT_IN : 0;

    const maxCols = Math.max(1, Math.floor((availW + gapIn) / (qrIn + gapIn)));
    const maxRows = Math.max(1, Math.floor((availH + gapIn) / ((qrIn + labelIn) + gapIn)));

    return { columns: maxCols, rows: maxRows };
});

const effectiveLayout = computed(() => {
    const maxL = maxLayoutThatFits.value;
    if (layout.value === 'auto') {
        return { ...maxL, name: `Auto (${maxL.columns}×${maxL.rows})` };
    }

    const req = requestedLayout.value;
    const cols = Math.max(1, Math.min(Number(req.columns ?? 2), Number(maxL.columns ?? 1)));
    const rows = Math.max(1, Math.min(Number(req.rows ?? 2), Number(maxL.rows ?? 1)));
    return { columns: cols, rows: rows, name: req.name ?? `${cols}×${rows}` };
});

// Backwards-compatible alias used by existing print/preview code paths.
const currentLayout = effectiveLayout;

const layoutWasClamped = computed(() => {
    if (layout.value === 'auto') return false;
    const req = requestedLayout.value;
    return (
        Number(effectiveLayout.value.columns) !== Number(req.columns) ||
        Number(effectiveLayout.value.rows) !== Number(req.rows)
    );
});

const previewQrPx = computed(() => {
    return Math.max(14, Math.round(qrSizeIn.value * PREVIEW_PX_PER_IN));
});

const previewGapPx = computed(() => {
    const gapIn = Math.max(0, Number(spacing.value || 0) / PRINT_DPI);
    return Math.round(gapIn * PREVIEW_PX_PER_IN);
});

// Track preview paper pixel size so we can clamp QR boxes to the available grid cell
const previewPaperEl = ref(null);
const previewPaperPx = ref({ width: 0, height: 0 });
let previewResizeObserver = null;

watch(headerFooterFont, (font) => {
    loadFontFamily(font);
});

onMounted(() => {
    preloadAllFonts();
    if (!previewPaperEl.value) return;
    previewResizeObserver = new ResizeObserver((entries) => {
        const entry = entries?.[0];
        if (!entry) return;
        const cr = entry.contentRect;
        previewPaperPx.value = { width: cr.width, height: cr.height };
    });
    previewResizeObserver.observe(previewPaperEl.value);
});

onBeforeUnmount(() => {
    try {
        previewResizeObserver?.disconnect();
    } catch (_) {
        // ignore
    }
});

const previewQrPxClamped = computed(() => {
    const cols = Number(effectiveLayout.value.columns || 1);
    const rows = Number(effectiveLayout.value.rows || 1);
    const gap = Number(previewGapPx.value || 0);

    const w = Number(previewPaperPx.value.width || 0);
    const h = Number(previewPaperPx.value.height || 0);
    if (!w || !h) return previewQrPx.value;

    const availW = Math.max(0, w - PREVIEW_PAPER_PADDING_PX * 2);
    const availH = Math.max(0, h - PREVIEW_PAPER_PADDING_PX * 2);

    const cellW = (availW - Math.max(0, cols - 1) * gap) / cols;
    const cellH = (availH - Math.max(0, rows - 1) * gap) / rows;

    const label = includeText.value ? PREVIEW_LABEL_PX : 0;
    const maxQr = Math.max(10, Math.min(cellW, cellH - label));

    return Math.max(10, Math.min(previewQrPx.value, maxQr));
});

// Get expanded QR codes list (with duplicates based on quantity)
const expandedQRCodes = computed(() => {
    const selected = printableQrCodes.value.filter(qr => selectedQRCodes.value.includes(qr.id));
    const expanded = [];
    for (let i = 0; i < quantity.value; i++) {
        expanded.push(...selected);
    }
    return expanded;
});

// Calculate preview grid
const previewGrid = computed(() => {
    const l = effectiveLayout.value;
    const grid = [];
    
    for (let i = 0; i < l.rows; i++) {
        const row = [];
        for (let j = 0; j < l.columns; j++) {
            const index = i * l.columns + j;
            row.push(expandedQRCodes.value[index] || null);
        }
        grid.push(row);
    }
    
    return grid;
});

// Number of pages needed
const pagesNeeded = computed(() => {
    const qrPerPage = effectiveLayout.value.columns * effectiveLayout.value.rows;
    return Math.ceil(expandedQRCodes.value.length / qrPerPage);
});

// Generate print sheet
const generateSheet = async (action = 'print') => {
    if (selectedQRCodes.value.length === 0) return;
    
    // IMPORTANT: If printing, open the window immediately (user gesture) to avoid popup blockers.
    // If it still fails, we'll fall back to iframe printing.
    let printWin = null;
    if (action === 'print') {
        printWin = window.open('', '_blank');
        if (printWin) {
            try {
                printWin.document.open();
                printWin.document.write('<!doctype html><title>Preparing print…</title><body style="font-family:Arial;padding:20px">Preparing your print preview…</body>');
                printWin.document.close();
            } catch (_) {
                // ignore
            }
        }
    }

    generating.value = true;
    
    try {
        let csrfToken = getCsrfToken();
        if (!csrfToken) {
            csrfToken = await refreshCsrfToken();
        }
        if (!csrfToken) {
            throw new Error('Missing CSRF token. Please refresh the page and try again.');
        }

        const buildPayload = () => JSON.stringify({
            qr_code_ids: selectedQRCodes.value,
            quantity: quantity.value,
            layout: layout.value,
            paper_size: paperSize.value,
            qr_size: qrSize.value,
            spacing: spacing.value,
            include_text: includeText.value,
            include_border: includeBorder.value,
            header_text: headerText.value || null,
            footer_text: footerText.value || null,
            header_footer_font: headerFooterFont.value || null,
            header_footer_color: headerFooterColor.value || null,
            header_font_size: headerFontSize.value || null,
            footer_font_size: footerFontSize.value || null,
            header_footer_outline: headerFooterOutline.value,
            header_footer_outline_color: headerFooterOutlineColor.value || null,
        });

        const doRequest = async (token) => {
            return fetch('/business/print-studio/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
                body: buildPayload(),
            });
        };

        let response = await doRequest(csrfToken);
        if (response.status === 419) {
            csrfToken = await refreshCsrfToken();
            if (csrfToken) {
                response = await doRequest(csrfToken);
            }
        }

        // If the session/CSRF expired, Laravel often returns HTML (login / 419 page).
        if (!response.ok) {
            if (response.status === 419) {
                closePrintWindow(printWin);
                alert('Session expired. Please refresh this page and try again.');
                return;
            }

            if (response.status === 401 || response.status === 403) {
                closePrintWindow(printWin);
                window.location.href = '/login';
                return;
            }

            const errText = await response.text().catch(() => '');
            throw new Error(`Request failed (${response.status}). ${errText ? 'Please refresh and try again.' : ''}`.trim());
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text().catch(() => '');
            // If we got redirected to login (session expired), don't try to parse.
            if ((response.url || '').includes('/login')) {
                closePrintWindow(printWin);
                window.location.href = '/login';
                return;
            }
            // If Laravel returned an HTML error page, a refresh usually fixes a stale CSRF token.
            if (text?.startsWith('<!DOCTYPE')) {
                closePrintWindow(printWin);
                throw new Error('Session expired. Please refresh and try again.');
            }
            throw new Error('Unexpected response (not JSON). Please try again.');
        }

        const data = await response.json();
        
        if (data.success) {
            previewData.value = data.sheet;
            const sheets = Array.isArray(data.sheet) ? data.sheet : [];
            
            if (action === 'print') {
                // Print in a clean window containing ONLY the preview pages
                if (printWin) {
                    writeToPrintWindow(printWin, sheets);
                } else {
                    // Popup blocked -> fallback
                    printViaIframe(sheets);
                }
            } else if (action === 'download') {
                // Download one PNG per sheet (qr-sheet-1.png, qr-sheet-2.png, ...)
                await downloadSheetsAsPng(sheets);
            }
        }
    } catch (error) {
        console.error('Generation failed:', error);
        closePrintWindow(printWin);
        alert('Failed to generate sheet. Please try again.');
    } finally {
        generating.value = false;
    }
};

</script>

<template>
    <Head title="Print Studio" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Print Studio</h1>
                <p class="text-gray-400 mt-1">Create printable QR code sheets</p>
            </div>

            <Link
                href="/business/print-kits"
                class="btn-primary whitespace-nowrap"
            >
                Sticker Kits
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Panel: QR Code Selection -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Templates -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Quick Templates</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button
                            v-for="template in templates"
                            :key="template.id"
                            @click="applyTemplate(template)"
                            class="p-4 rounded-xl border border-white/20 hover:border-primary-500 hover:bg-primary-500/10 transition-all text-left"
                        >
                            <p class="text-white font-medium text-sm">{{ template.name }}</p>
                            <p class="text-gray-500 text-xs mt-1">{{ template.description }}</p>
                        </button>
                    </div>
                </div>

                <!-- QR Code Selection -->
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-white">Select QR Codes</h3>
                        <div class="space-x-2">
                            <button @click="selectAll" class="text-primary-400 text-sm hover:underline">
                                Select All
                            </button>
                            <button @click="clearSelection" class="text-gray-400 text-sm hover:underline">
                                Clear
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="printableQrCodes.length" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button
                            v-for="qr in printableQrCodes"
                            :key="qr.id"
                            @click="toggleQRCode(qr.id)"
                            :class="[
                                'p-4 rounded-xl border-2 text-left transition-all',
                                selectedQRCodes.includes(qr.id)
                                    ? 'border-primary-500 bg-primary-500/20'
                                    : 'border-white/20 hover:border-white/40'
                            ]"
                        >
                            <!-- Mini QR Preview -->
                            <div class="w-12 h-12 bg-white rounded-lg mx-auto mb-2 flex items-center justify-center overflow-hidden p-1">
                                <img 
                                    v-if="getQRImageUrl(qr)" 
                                    :src="getQRImageUrl(qr)" 
                                    :alt="qr.name"
                                    class="w-full h-full object-contain"
                                />
                                <svg v-else class="w-10 h-10 text-gray-800" viewBox="0 0 100 100">
                                    <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                </svg>
                            </div>
                            <p class="text-white text-sm font-medium truncate">{{ qr.name }}</p>
                            <p class="text-gray-500 text-xs">{{ qr.code }}</p>
                        </button>
                    </div>
                    <p v-else class="text-gray-500 text-center py-8">
                        No QR codes yet. Create some first!
                    </p>
                </div>

                <!-- Settings -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Print Settings</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Layout -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Layout</label>
                            <select v-model="layout" class="input-glass">
                                <option v-for="(config, key) in layouts" :key="key" :value="key" class="bg-gray-800 text-white">
                                    {{ config.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Paper Size -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Paper Size</label>
                            <select v-model="paperSize" class="input-glass">
                                <option v-for="(config, key) in paperSizes" :key="key" :value="key" class="bg-gray-800 text-white">
                                    {{ config.name }}
                                </option>
                            </select>
                        </div>

                        <!-- QR Size -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                QR Code Size: {{ qrSize }}px
                                <span class="text-gray-500">(~{{ qrSizeIn.toFixed(2) }}" / {{ qrSizeMm.toFixed(0) }}mm)</span>
                            </label>
                            <div class="flex items-center gap-2 mb-2">
                                <button type="button" class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-gray-200 text-xs hover:bg-white/20" @click="setQrSizeInches(1.5)">
                                    1.5"
                                </button>
                                <button type="button" class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-gray-200 text-xs hover:bg-white/20" @click="setQrSizeInches(2)">
                                    2"
                                </button>
                                <button type="button" class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-gray-200 text-xs hover:bg-white/20" @click="setQrSizeInches(4)">
                                    4"
                                </button>
                                <span class="text-xs text-gray-500 ml-1">Presets</span>
                            </div>
                            <input type="range" v-model="qrSize" min="50" max="1200" class="w-full" />
                            <p class="text-gray-500 text-xs mt-1">Tip: Use <span class="text-gray-300">Auto (fit by size)</span> for accurate per-page counts at large sizes.</p>
                        </div>

                        <!-- Spacing -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Spacing: {{ spacing }}px</label>
                            <input type="range" v-model="spacing" min="0" max="50" class="w-full" />
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Copies per QR Code: {{ quantity }}</label>
                            <input type="number" v-model.number="quantity" min="1" max="100" class="input-glass" />
                            <p class="text-gray-500 text-xs mt-1">How many times each selected QR code should appear</p>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-white/10 pt-4">
                        <h4 class="text-sm font-semibold text-white mb-3">Header / Footer Text (Optional)</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Header</label>
                                <div class="space-y-2">
                                    <label v-for="option in headerFooterOptions" :key="`header-${option}`" class="flex items-center gap-2 text-sm text-gray-300">
                                        <input
                                            type="checkbox"
                                            class="rounded border-white/20 bg-white/10 text-primary-500"
                                            :checked="headerText === option"
                                            @change="headerText = headerText === option ? '' : option"
                                        />
                                        <span>{{ option }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Footer</label>
                                <div class="space-y-2">
                                    <label v-for="option in headerFooterOptions" :key="`footer-${option}`" class="flex items-center gap-2 text-sm text-gray-300">
                                        <input
                                            type="checkbox"
                                            class="rounded border-white/20 bg-white/10 text-primary-500"
                                            :checked="footerText === option"
                                            @change="footerText = footerText === option ? '' : option"
                                        />
                                        <span>{{ option }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Font</label>
                                <select v-model="headerFooterFont" class="input-glass">
                                    <option v-for="(label, font) in (fonts || {})" :key="font" :value="font" class="bg-gray-800 text-white">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Text Color</label>
                                <input type="color" v-model="headerFooterColor" class="h-10 w-full rounded-lg border border-white/10 bg-white/5" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Header Size (pt)</label>
                                <input type="number" v-model.number="headerFontSize" min="8" max="72" class="input-glass" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-2">Footer Size (pt)</label>
                                <input type="number" v-model.number="footerFontSize" min="8" max="72" class="input-glass" />
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 text-sm text-gray-300">
                                    <input type="checkbox" v-model="headerFooterOutline" class="rounded border-white/20 bg-white/10 text-primary-500" />
                                    Outline
                                </label>
                                <input type="color" v-model="headerFooterOutlineColor" class="h-10 w-24 rounded-lg border border-white/10 bg-white/5" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-6 mt-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" v-model="includeText" class="rounded border-white/20 bg-white/10 text-primary-500" />
                            <span class="ml-2 text-gray-300 text-sm">Include QR name</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" v-model="includeBorder" class="rounded border-white/20 bg-white/10 text-primary-500" />
                            <span class="ml-2 text-gray-300 text-sm">Add cut lines</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Preview -->
            <div class="space-y-6">
                <!-- Preview Card -->
                <div class="glass-card p-6 sticky top-24">
                    <h3 class="text-lg font-semibold text-white mb-4">Preview</h3>
                    <p v-if="layoutWasClamped" class="text-xs text-amber-300 mb-3">
                        Layout adjusted to {{ effectiveLayout.columns }}×{{ effectiveLayout.rows }} to keep your QR size at ~{{ qrSizeIn.toFixed(2) }}" on {{ currentPaper.name }}.
                    </p>
                    
                    <!-- Paper Preview (Display) -->
                    <div
                        ref="previewPaperEl"
                        class="bg-white rounded-lg p-4 shadow-lg w-full relative"
                        :style="{ aspectRatio: `${currentPaper.width} / ${currentPaper.height}` }"
                    >
                        <div
                            v-if="headerText"
                            class="absolute left-4 right-4 top-2 text-center font-semibold"
                            :style="headerStyle"
                        >
                            {{ headerText }}
                        </div>
                        <div
                            v-if="footerText"
                            class="absolute left-4 right-4 bottom-2 text-center font-semibold"
                            :style="footerStyle"
                        >
                            {{ footerText }}
                        </div>
                        <div 
                            class="w-full h-full grid gap-2"
                            :style="{
                                gridTemplateColumns: `repeat(${effectiveLayout.columns}, 1fr)`,
                                gridTemplateRows: `repeat(${effectiveLayout.rows}, 1fr)`,
                                gap: `${previewGapPx}px`
                            }"
                        >
                            <template v-for="(row, i) in previewGrid" :key="`row-${i}`">
                            <div
                                v-for="(qr, j) in row"
                                :key="`${i}-${j}`"
                                class="flex items-center justify-center"
                            >
                                <div v-if="qr" class="text-center">
                                    <div 
                                        class="bg-white rounded flex items-center justify-center mx-auto overflow-hidden"
                                        :class="includeBorder ? 'border border-dashed border-gray-300' : ''"
                                        :style="{ width: `${previewQrPxClamped}px`, height: `${previewQrPxClamped}px` }"
                                    >
                                        <img 
                                            v-if="getQRImageUrl(qr)" 
                                            :src="getQRImageUrl(qr)" 
                                            :alt="qr.name"
                                            class="w-full h-full object-contain p-0.5"
                                        />
                                        <svg v-else class="w-full h-full p-1 text-gray-800" viewBox="0 0 100 100">
                                            <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                            <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                            <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <p v-if="includeText" class="text-gray-600 text-[6px] mt-0.5 truncate max-w-[120px]">
                                        {{ qr.name }}
                                    </p>
                                </div>
                                <div v-else class="w-8 h-8 border border-dashed border-gray-200 rounded"></div>
                            </div>
                            </template>
                        </div>
                    </div>
                    

                    <!-- Info -->
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Selected QR Codes:</span>
                            <span class="text-white">{{ selectedQRCodes.length }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Copies per QR:</span>
                            <span class="text-white">{{ quantity }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total QR Codes:</span>
                            <span class="text-white">{{ expandedQRCodes.length }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">QR per page:</span>
                            <span class="text-white">{{ effectiveLayout.columns * effectiveLayout.rows }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Pages needed:</span>
                            <span class="text-white">{{ pagesNeeded }}</span>
                        </div>
                    </div>

                    <!-- Generate Buttons -->
                    <div class="mt-6 space-y-3">
                        <button
                            @click="generateSheet('print')"
                            :disabled="generating || selectedQRCodes.length === 0"
                            class="w-full btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="generating">Generating...</span>
                            <span v-else>Generate & Print</span>
                        </button>
                        <button
                            @click="generateSheet('download')"
                            :disabled="generating || selectedQRCodes.length === 0"
                            class="w-full bg-white/10 text-white rounded-xl py-3 font-semibold hover:bg-white/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="generating">Generating...</span>
                            <span v-else>Generate & Download</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Preview (Hidden, shown only when printing) -->
    <div id="print-preview" style="display: none;" class="print-only">
        <div 
            class="qr-grid"
            :style="{
                gridTemplateColumns: `repeat(${currentLayout.columns}, 1fr)`,
                gridTemplateRows: `repeat(${currentLayout.rows}, 1fr)`,
                gap: `${spacing}px`,
                padding: '0.5in',
                width: '8.5in',
                height: '11in',
                margin: '0 auto'
            }"
        >
            <template v-for="(row, i) in previewGrid" :key="`print-row-${i}`">
            <div
                v-for="(qr, j) in row"
                :key="`print-${i}-${j}`"
                class="qr-item"
            >
                <div v-if="qr" class="text-center" style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div 
                        class="qr-image"
                        :class="includeBorder ? 'border-2 border-dashed border-gray-400' : ''"
                        style="width: 100%; height: auto; max-width: 100%; max-height: calc(100% - 20px); aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center;"
                    >
                        <img 
                            v-if="getQRImageUrl(qr)" 
                            :src="getQRImageUrl(qr)" 
                            :alt="qr.name"
                            style="width: 100%; height: 100%; object-fit: contain; max-width: 100%; max-height: 100%;"
                        />
                        <svg v-else style="width: 100%; height: 100%;" class="text-gray-800" viewBox="0 0 100 100">
                            <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                            <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                            <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                        </svg>
                    </div>
                    <p v-if="includeText" class="qr-name" style="font-size: 10px; margin-top: 4px; color: #000;">
                        {{ qr.name }}
                    </p>
                </div>
            </div>
            </template>
        </div>
    </div>

    <!-- Print-only styles -->
    <style>
    .print-only {
        position: absolute;
        left: -9999px;
        top: -9999px;
        visibility: hidden;
    }
    
    @media print {
        /* Hide EVERYTHING by default - be very aggressive */
        html > *:not(#print-preview),
        body > *:not(#print-preview),
        head > * {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Show only the print preview and its children */
        #print-preview,
        #print-preview * {
            visibility: visible !important;
        }
        
        /* Show only the print preview */
        #print-preview {
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            display: block !important;
            z-index: 99999 !important;
            overflow: visible !important;
        }
        
        /* Ensure grid layout for QR codes - use actual paper dimensions */
        #print-preview .qr-grid {
            display: grid !important;
            width: 8.5in !important;
            height: 11in !important;
            margin: 0 auto !important;
            padding: 0.5in !important;
            background: white !important;
            box-sizing: border-box !important;
        }
        
        /* QR items should fill their grid cells */
        #print-preview .qr-item {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* QR images should fit within their container */
        #print-preview .qr-image {
            width: 100% !important;
            height: auto !important;
            max-width: 100% !important;
            max-height: calc(100% - 20px) !important;
            aspect-ratio: 1 / 1 !important;
            box-sizing: border-box !important;
        }
        
        #print-preview .qr-name {
            font-size: 10px !important;
            margin-top: 4px !important;
            color: #000 !important;
        }
        
        /* Ensure proper page size */
        @page {
            size: letter;
            margin: 0;
        }
        
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            width: 100% !important;
            height: 100% !important;
        }
        
        html {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }
    }
    </style>
</template>


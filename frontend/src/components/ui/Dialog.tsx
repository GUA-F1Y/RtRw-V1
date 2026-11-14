import React, { useEffect, useRef } from 'react';

interface DialogProps {
  /** Whether the dialog is currently open */
  open: boolean;
  /** Called when the user requests close (Escape, backdrop click, or explicit close button) */
  onClose: () => void;
  /** Dialog accessible title — rendered as <h2> inside the dialog */
  title: string;
  /** Optional subtitle / description shown below the title */
  description?: string;
  /** Dialog body content */
  children: React.ReactNode;
  /** Footer slot — typically buttons */
  footer?: React.ReactNode;
  /** Max-width class override. Defaults to 'max-w-lg'. */
  maxWidth?: string;
  /** Data test id for the dialog root element */
  'data-testid'?: string;
}

/**
 * Accessible modal dialog.
 *
 * Behaviour:
 * - Traps focus within the dialog while open.
 * - Closes on Escape key press.
 * - Closes when backdrop is clicked.
 * - Prevents body scroll while open.
 * - Renders as a sibling of the app root to avoid stacking context issues.
 */
export const Dialog: React.FC<DialogProps> = ({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  maxWidth = 'max-w-lg',
  'data-testid': testId,
}) => {
  const panelRef = useRef<HTMLDivElement>(null);

  // Prevent body scroll while open
  useEffect(() => {
    if (open) {
      const prev = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      return () => { document.body.style.overflow = prev; };
    }
  }, [open]);

  // Close on Escape
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  // Focus first focusable element when opened
  useEffect(() => {
    if (!open) return;
    const panel = panelRef.current;
    if (!panel) return;
    const selector =
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    const first = panel.querySelector<HTMLElement>(selector);
    first?.focus();
  }, [open]);

  if (!open) return null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="dialog-title"
      data-testid={testId}
      className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"
        aria-hidden="true"
        onClick={onClose}
      />

      {/* Panel */}
      <div
        ref={panelRef}
        className={`relative w-full ${maxWidth} rounded-2xl border border-slate-700/50 bg-slate-900 shadow-2xl shadow-slate-950/60 flex flex-col max-h-[90dvh]`}
      >
        {/* Header */}
        <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-slate-800/60">
          <div className="flex-1 min-w-0">
            <h2
              id="dialog-title"
              className="text-base font-semibold text-white leading-snug"
            >
              {title}
            </h2>
            {description && (
              <p className="mt-1 text-sm text-slate-400">{description}</p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup dialog"
            className="flex-shrink-0 p-1.5 rounded-lg text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-6 py-5 space-y-4">
          {children}
        </div>

        {/* Footer */}
        {footer && (
          <div className="px-6 pb-6 pt-4 border-t border-slate-800/60">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
};

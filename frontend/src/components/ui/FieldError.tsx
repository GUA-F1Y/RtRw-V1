import React from 'react';

/**
 * Displays a single field-level validation error message.
 * Used consistently in every form dialog to show server-returned errors.
 */
export const FieldError: React.FC<{ message?: string | null; id?: string }> = ({
  message,
  id,
}) => {
  if (!message) return null;
  return (
    <p id={id} className="mt-1 text-xs text-rose-400" role="alert" aria-live="polite">
      {message}
    </p>
  );
};

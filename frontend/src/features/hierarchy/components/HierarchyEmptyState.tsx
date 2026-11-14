import React from 'react';

interface HierarchyEmptyStateProps {
  variant: 'no-organizations' | 'no-rws' | 'no-rts';
}

const CONTENT = {
  'no-organizations': {
    title: 'Tidak ada organisasi yang dapat diakses',
    description:
      'Akun Anda belum ditetapkan dalam organisasi manapun. Hubungi administrator sistem untuk mendapatkan akses.',
    icon: (
      <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.25}
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
        />
      </svg>
    ),
  },
  'no-rws': {
    title: 'Belum ada RW yang terdaftar',
    description:
      'Organisasi ini belum memiliki unit RW yang terdaftar. Kontak administrator untuk menambahkan unit RW.',
    icon: (
      <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.25}
          d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
        />
      </svg>
    ),
  },
  'no-rts': {
    title: 'RW ini belum memiliki unit RT',
    description:
      'Belum ada RT yang terdaftar dalam RW ini. Tambahkan unit RT melalui administrator.',
    icon: (
      <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.25}
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
        />
      </svg>
    ),
  },
};

export const HierarchyEmptyState: React.FC<HierarchyEmptyStateProps> = ({ variant }) => {
  const content = CONTENT[variant];

  return (
    <div
      className="flex flex-col items-center justify-center text-center py-16 px-6 rounded-2xl border border-dashed border-slate-800 bg-slate-950/30"
      role="status"
      aria-label={content.title}
    >
      <div className="text-slate-600 mb-4">{content.icon}</div>
      <h3 className="text-base font-semibold text-slate-300 mb-2">{content.title}</h3>
      <p className="text-sm text-slate-500 max-w-md leading-relaxed">{content.description}</p>
    </div>
  );
};

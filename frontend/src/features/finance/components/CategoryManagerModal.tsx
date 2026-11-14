import React, { useState } from 'react';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type {
  CreateFinanceCategoryPayload,
  FinanceCategory,
  TransactionType,
  UpdateFinanceCategoryPayload,
} from '../../../types/finance.ts';
import {
  useCreateFinanceCategory,
  useFinanceCategories,
  useUpdateFinanceCategory,
} from '../api/financeQueries.ts';
import { canManageFinanceCategories } from '../utils/permissions.ts';

interface CategoryManagerModalProps {
  open: boolean;
  onClose: () => void;
}

export const CategoryManagerModal: React.FC<CategoryManagerModalProps> = ({
  open,
  onClose,
}) => {
  const { user } = useAuth();
  const canManage = canManageFinanceCategories(user);

  const [activeTab, setActiveTab] = useState<'ALL' | 'INCOME' | 'EXPENSE'>('ALL');
  const [editingCategory, setEditingCategory] = useState<FinanceCategory | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  // Form states
  const [name, setName] = useState('');
  const [type, setType] = useState<TransactionType>('INCOME');
  const [code, setCode] = useState('');
  const [description, setDescription] = useState('');
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const { data: catResponse, isLoading } = useFinanceCategories(
    activeTab === 'ALL' ? undefined : { type: activeTab },
    { enabled: open }
  );

  const createMutation = useCreateFinanceCategory();
  const updateMutation = useUpdateFinanceCategory();

  const categories = catResponse?.data ?? [];

  const handleStartCreate = () => {
    setIsCreating(true);
    setEditingCategory(null);
    setName('');
    setType(activeTab === 'EXPENSE' ? 'EXPENSE' : 'INCOME');
    setCode('');
    setDescription('');
    setClientErrors({});
    createMutation.reset();
  };

  const handleStartEdit = (cat: FinanceCategory) => {
    setEditingCategory(cat);
    setIsCreating(false);
    setName(cat.name);
    setCode(cat.code || '');
    setDescription(cat.description || '');
    setClientErrors({});
    updateMutation.reset();
  };

  const handleCancelForm = () => {
    setIsCreating(false);
    setEditingCategory(null);
    setClientErrors({});
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      setClientErrors({ name: 'Nama kategori wajib diisi.' });
      return;
    }

    setClientErrors({});

    if (isCreating) {
      const payload: CreateFinanceCategoryPayload = {
        name: name.trim(),
        type,
        code: code.trim() || undefined,
        description: description.trim() || undefined,
      };

      try {
        await createMutation.mutateAsync({ payload });
        handleCancelForm();
      } catch {
        // Handled by createMutation.error
      }
    } else if (editingCategory) {
      const payload: UpdateFinanceCategoryPayload = {
        name: name.trim(),
        code: code.trim() || undefined,
        description: description.trim() || undefined,
      };

      try {
        await updateMutation.mutateAsync({ id: editingCategory.id, payload });
        handleCancelForm();
      } catch {
        // Handled by updateMutation.error
      }
    }
  };

  const activeMutation = isCreating ? createMutation : updateMutation;
  const validationErrors = activeMutation.error?.errors;
  const generalError = activeMutation.error?.message;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Manajemen Kategori Keuangan"
      description="Kategori pos anggaran pemasukan dan pengeluaran kas RT/RW."
      maxWidth="max-w-2xl"
    >
      <div className="space-y-4">
        {/* Tab filters & Add button */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-3">
          <div className="flex items-center gap-1.5 bg-slate-950/60 p-1 rounded-lg border border-slate-800">
            <button
              type="button"
              onClick={() => { setActiveTab('ALL'); handleCancelForm(); }}
              className={`px-3 py-1 text-xs font-medium rounded-md transition-all ${
                activeTab === 'ALL'
                  ? 'bg-slate-800 text-white shadow-sm'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Semua ({categories.length})
            </button>
            <button
              type="button"
              onClick={() => { setActiveTab('INCOME'); handleCancelForm(); }}
              className={`px-3 py-1 text-xs font-medium rounded-md transition-all ${
                activeTab === 'INCOME'
                  ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-800/60'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Pemasukan
            </button>
            <button
              type="button"
              onClick={() => { setActiveTab('EXPENSE'); handleCancelForm(); }}
              className={`px-3 py-1 text-xs font-medium rounded-md transition-all ${
                activeTab === 'EXPENSE'
                  ? 'bg-rose-950/80 text-rose-300 border border-rose-800/60'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Pengeluaran
            </button>
          </div>

          {canManage && !isCreating && !editingCategory && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={handleStartCreate}
              className="text-xs"
            >
              + Tambah Kategori
            </Button>
          )}
        </div>

        {/* Inline Create / Edit Form */}
        {(isCreating || editingCategory) && (
          <div className="p-4 rounded-xl bg-slate-950/70 border border-slate-800 space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="text-xs font-semibold text-slate-200 uppercase tracking-wider">
                {isCreating ? 'Tambah Kategori Baru' : `Edit Kategori: ${editingCategory?.name}`}
              </h4>
              <button
                type="button"
                onClick={handleCancelForm}
                className="text-xs text-slate-400 hover:text-slate-200"
              >
                Tutup Form
              </button>
            </div>

            {generalError && (
              <Alert
                type="error"
                title="Gagal Menyimpan Kategori"
                message={generalError}
                requestId={activeMutation.error?.requestId}
              />
            )}

            <form onSubmit={handleSave} className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {/* Name */}
                <div className="space-y-1">
                  <label className="block text-[11px] font-medium text-slate-300">
                    Nama Kategori <span className="text-rose-400">*</span>
                  </label>
                  <input
                    type="text"
                    value={name}
                    placeholder="cth. Iuran Kebersihan, Perbaikan Lampu Jalan"
                    onChange={(e) => setName(e.target.value)}
                    className="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                  />
                  <FieldError message={clientErrors.name || validationErrors?.name?.[0]} />
                </div>

                {/* Direction (Only during creation) */}
                {isCreating ? (
                  <div className="space-y-1">
                    <label className="block text-[11px] font-medium text-slate-300">
                      Jenis Arus Kas <span className="text-rose-400">*</span>
                    </label>
                    <select
                      value={type}
                      onChange={(e) => setType(e.target.value as TransactionType)}
                      className="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                    >
                      <option value="INCOME">Pemasukan (INCOME)</option>
                      <option value="EXPENSE">Pengeluaran (EXPENSE)</option>
                    </select>
                    <FieldError message={validationErrors?.type?.[0]} />
                  </div>
                ) : (
                  <div className="space-y-1">
                    <span className="block text-[11px] font-medium text-slate-400">
                      Jenis Arus Kas (Terkunci)
                    </span>
                    <div className="pt-1">
                      <Badge variant={editingCategory?.type === 'INCOME' ? 'emerald' : 'rose'} size="sm">
                        {editingCategory?.type}
                      </Badge>
                    </div>
                  </div>
                )}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {/* Code */}
                <div className="space-y-1">
                  <label className="block text-[11px] font-medium text-slate-300">
                    Kode Kategori (Opsional)
                  </label>
                  <input
                    type="text"
                    value={code}
                    placeholder="cth. INC-01, EXP-MAINT"
                    onChange={(e) => setCode(e.target.value)}
                    className="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                  />
                  <FieldError message={validationErrors?.code?.[0]} />
                </div>

                {/* Description */}
                <div className="space-y-1">
                  <label className="block text-[11px] font-medium text-slate-300">
                    Deskripsi Kategori (Opsional)
                  </label>
                  <input
                    type="text"
                    value={description}
                    placeholder="Keterangan singkat pos anggaran..."
                    onChange={(e) => setDescription(e.target.value)}
                    className="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                  />
                  <FieldError message={validationErrors?.description?.[0]} />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={handleCancelForm}
                  disabled={activeMutation.isPending}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  size="sm"
                  isLoading={activeMutation.isPending}
                  disabled={activeMutation.isPending}
                >
                  {isCreating ? 'Tambah' : 'Simpan'}
                </Button>
              </div>
            </form>
          </div>
        )}

        {/* Category List */}
        <div className="max-h-72 overflow-y-auto space-y-2 pr-1">
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-slate-800/40 rounded-lg animate-pulse" />
              ))}
            </div>
          ) : categories.length === 0 ? (
            <div className="py-8 text-center text-xs text-slate-400">
              Belum ada kategori terdaftar pada cakupan ini.
            </div>
          ) : (
            categories.map((cat) => (
              <div
                key={cat.id}
                className="flex items-center justify-between p-3 rounded-lg bg-slate-900/60 border border-slate-800 hover:border-slate-700 transition-colors"
              >
                <div className="flex items-center gap-2.5">
                  <Badge variant={cat.type === 'INCOME' ? 'emerald' : 'rose'} size="sm">
                    {cat.type}
                  </Badge>
                  <div>
                    <div className="text-xs font-semibold text-slate-100 flex items-center gap-2">
                      <span>{cat.name}</span>
                      {cat.code && (
                        <span className="font-mono text-[10px] text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded">
                          {cat.code}
                        </span>
                      )}
                    </div>
                    {cat.description && (
                      <div className="text-[11px] text-slate-400 line-clamp-1">{cat.description}</div>
                    )}
                  </div>
                </div>

                {canManage && (
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={() => handleStartEdit(cat)}
                    className="text-xs px-2.5 py-1"
                  >
                    Edit
                  </Button>
                )}
              </div>
            ))
          )}
        </div>
      </div>
    </Dialog>
  );
};

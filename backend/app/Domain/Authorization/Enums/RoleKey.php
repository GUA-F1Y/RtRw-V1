<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * System roles, their grant level and the permissions they carry.
 *
 * This is the single source of truth: RolePermissionSeeder syncs it into the
 * roles/permissions tables. A role is only valid at its own scope level.
 */
enum RoleKey: string
{
    case OrganizationAdmin = 'organization_admin';
    case KetuaRw = 'ketua_rw';
    case SekretarisRw = 'sekretaris_rw';
    case BendaharaRw = 'bendahara_rw';
    case KetuaRt = 'ketua_rt';
    case SekretarisRt = 'sekretaris_rt';
    case BendaharaRt = 'bendahara_rt';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationAdmin => 'Organization Administrator',
            self::KetuaRw => 'Ketua RW',
            self::SekretarisRw => 'Sekretaris RW',
            self::BendaharaRw => 'Bendahara RW',
            self::KetuaRt => 'Ketua RT',
            self::SekretarisRt => 'Sekretaris RT',
            self::BendaharaRt => 'Bendahara RT',
        };
    }

    public function scopeLevel(): ScopeLevel
    {
        return match ($this) {
            self::OrganizationAdmin => ScopeLevel::Organization,
            self::KetuaRw, self::SekretarisRw, self::BendaharaRw => ScopeLevel::Rw,
            self::KetuaRt, self::SekretarisRt, self::BendaharaRt => ScopeLevel::Rt,
        };
    }

    /**
     * @return list<PermissionKey>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OrganizationAdmin => PermissionKey::cases(),

            self::KetuaRw => [
                PermissionKey::RwView,
                PermissionKey::RtView,
                PermissionKey::RtManage,
                PermissionKey::MembershipView,
                PermissionKey::MembershipManage,
                PermissionKey::AuditView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,
                PermissionKey::ResidentViewSensitive,
                PermissionKey::ResidentRegister,
                PermissionKey::ResidentVerify,
                PermissionKey::ResidentUpdate,
                PermissionKey::ResidentRelocate,
                PermissionKey::ResidentRemoveFromHousehold,
                PermissionKey::ResidentChangeStatus,

                PermissionKey::HouseholdView,
                PermissionKey::HouseholdViewSensitive,
                PermissionKey::HouseholdCreate,
                PermissionKey::HouseholdUpdate,
                PermissionKey::HouseholdAddMember,
                PermissionKey::HouseholdRemoveMember,
                PermissionKey::HouseholdChangeHead,
                PermissionKey::HouseholdMove,
                PermissionKey::HouseholdDissolve,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceAccountManage,
                PermissionKey::FinanceCategoryManage,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransactionRecord,
                PermissionKey::FinanceTransactionReverse,
                PermissionKey::FinancePeriodClose,
                PermissionKey::FinanceTransferView,
                PermissionKey::FinanceTransferCreate,
                PermissionKey::FinanceTransferReverse,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceBudgetManage,
                PermissionKey::FinanceBudgetPublish,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
                PermissionKey::FinanceExpenditureApprove,
            ],

            self::SekretarisRw => [
                PermissionKey::RwView,
                PermissionKey::RtView,
                PermissionKey::MembershipView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,
                PermissionKey::ResidentViewSensitive,
                PermissionKey::ResidentRegister,
                PermissionKey::ResidentVerify,
                PermissionKey::ResidentUpdate,

                PermissionKey::HouseholdView,
                PermissionKey::HouseholdViewSensitive,
                PermissionKey::HouseholdCreate,
                PermissionKey::HouseholdUpdate,
                PermissionKey::HouseholdAddMember,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransferView,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
            ],

            self::BendaharaRw => [
                PermissionKey::RwView,
                PermissionKey::RtView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,

                PermissionKey::HouseholdView,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceAccountManage,
                PermissionKey::FinanceCategoryManage,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransactionRecord,
                PermissionKey::FinanceTransactionReverse,
                PermissionKey::FinancePeriodClose,
                PermissionKey::FinanceTransferView,
                PermissionKey::FinanceTransferCreate,
                PermissionKey::FinanceTransferReverse,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceBudgetManage,
                PermissionKey::FinanceBudgetPublish,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
                PermissionKey::FinanceExpenditurePost,
            ],

            self::KetuaRt => [
                PermissionKey::RtView,
                PermissionKey::MembershipView,
                PermissionKey::MembershipManage,
                PermissionKey::AuditView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,
                PermissionKey::ResidentViewSensitive,
                PermissionKey::ResidentRegister,
                PermissionKey::ResidentVerify,
                PermissionKey::ResidentUpdate,
                PermissionKey::ResidentRelocate,
                PermissionKey::ResidentRemoveFromHousehold,
                PermissionKey::ResidentChangeStatus,

                PermissionKey::HouseholdView,
                PermissionKey::HouseholdViewSensitive,
                PermissionKey::HouseholdCreate,
                PermissionKey::HouseholdUpdate,
                PermissionKey::HouseholdAddMember,
                PermissionKey::HouseholdRemoveMember,
                PermissionKey::HouseholdChangeHead,
                PermissionKey::HouseholdMove,
                PermissionKey::HouseholdDissolve,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceAccountManage,
                PermissionKey::FinanceCategoryManage,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransactionRecord,
                PermissionKey::FinanceTransactionReverse,
                PermissionKey::FinancePeriodClose,
                PermissionKey::FinanceTransferView,
                PermissionKey::FinanceTransferCreate,
                PermissionKey::FinanceTransferReverse,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceBudgetManage,
                PermissionKey::FinanceBudgetPublish,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
                PermissionKey::FinanceExpenditureApprove,
            ],

            self::SekretarisRt => [
                PermissionKey::RtView,
                PermissionKey::MembershipView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,
                PermissionKey::ResidentViewSensitive,
                PermissionKey::ResidentRegister,
                PermissionKey::ResidentVerify,
                PermissionKey::ResidentUpdate,
                PermissionKey::ResidentRemoveFromHousehold,

                PermissionKey::HouseholdView,
                PermissionKey::HouseholdViewSensitive,
                PermissionKey::HouseholdCreate,
                PermissionKey::HouseholdUpdate,
                PermissionKey::HouseholdAddMember,
                PermissionKey::HouseholdRemoveMember,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransferView,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
            ],

            self::BendaharaRt => [
                PermissionKey::RtView,
                PermissionKey::FileView,
                PermissionKey::FileUpload,

                PermissionKey::ResidentDirectoryView,
                PermissionKey::ResidentView,

                PermissionKey::HouseholdView,

                PermissionKey::FinanceAccountView,
                PermissionKey::FinanceAccountManage,
                PermissionKey::FinanceCategoryManage,
                PermissionKey::FinanceTransactionView,
                PermissionKey::FinanceTransactionRecord,
                PermissionKey::FinanceTransactionReverse,
                PermissionKey::FinancePeriodClose,
                PermissionKey::FinanceTransferView,
                PermissionKey::FinanceTransferCreate,
                PermissionKey::FinanceTransferReverse,

                PermissionKey::FinanceBudgetView,
                PermissionKey::FinanceBudgetManage,
                PermissionKey::FinanceBudgetPublish,
                PermissionKey::FinanceExpenditureView,
                PermissionKey::FinanceExpenditureRequest,
                PermissionKey::FinanceExpenditurePost,
            ],
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * Every permission known to the platform. Modules add their own cases here as
 * they are built; RolePermissionSeeder syncs them into the database.
 */
enum PermissionKey: string
{
    // Foundation Permissions
    case RwView = 'rw.view';
    case RwManage = 'rw.manage';
    case RtView = 'rt.view';
    case RtManage = 'rt.manage';
    case MembershipView = 'membership.view';
    case MembershipManage = 'membership.manage';
    case AuditView = 'audit.view';
    case FileView = 'file.view';
    case FileUpload = 'file.upload';

    // Resident Domain Permissions
    case ResidentDirectoryView = 'resident.directory_view';
    case ResidentView = 'resident.view';
    case ResidentViewSensitive = 'resident.view_sensitive';
    case ResidentRegister = 'resident.register';
    case ResidentVerify = 'resident.verify';
    case ResidentUpdate = 'resident.update';
    case ResidentRelocate = 'resident.relocate';
    case ResidentRemoveFromHousehold = 'resident.remove_from_household';
    case ResidentChangeStatus = 'resident.change_status';

    // Household Domain Permissions
    case HouseholdView = 'household.view';
    case HouseholdViewSensitive = 'household.view_sensitive';
    case HouseholdCreate = 'household.create';
    case HouseholdUpdate = 'household.update';
    case HouseholdAddMember = 'household.add_member';
    case HouseholdRemoveMember = 'household.remove_member';
    case HouseholdChangeHead = 'household.change_head';
    case HouseholdMove = 'household.move';
    case HouseholdDissolve = 'household.dissolve';

    // Finance Domain Permissions
    case FinanceAccountView = 'finance.account_view';
    case FinanceAccountManage = 'finance.account_manage';
    case FinanceCategoryManage = 'finance.category_manage';
    case FinanceTransactionView = 'finance.transaction_view';
    case FinanceTransactionRecord = 'finance.transaction_record';
    case FinanceTransactionReverse = 'finance.transaction_reverse';
    case FinancePeriodClose = 'finance.period.close';
    case FinanceTransferView = 'finance.transfer.view';
    case FinanceTransferCreate = 'finance.transfer.create';
    case FinanceTransferReverse = 'finance.transfer.reverse';

    // Budget & Expenditure Permissions (Phase 27)
    case FinanceBudgetView = 'finance.budget.view';
    case FinanceBudgetManage = 'finance.budget.manage';
    case FinanceBudgetPublish = 'finance.budget.publish';
    case FinanceExpenditureView = 'finance.expenditure.view';
    case FinanceExpenditureRequest = 'finance.expenditure.request';
    case FinanceExpenditureApprove = 'finance.expenditure.approve';
    case FinanceExpenditurePost = 'finance.expenditure.post';

    public function description(): string
    {
        return match ($this) {
            self::RwView => 'View RW units',
            self::RwManage => 'Create and manage RW units',
            self::RtView => 'View RT units',
            self::RtManage => 'Create and manage RT units',
            self::MembershipView => 'View memberships (role assignments) in a scope',
            self::MembershipManage => 'Assign and revoke memberships in a scope',
            self::AuditView => 'View the audit log of a scope',
            self::FileView => 'View and download files of a scope',
            self::FileUpload => 'Upload files into a scope',

            self::ResidentDirectoryView => 'View public community resident directory without sensitive fields',
            self::ResidentView => 'View resident record non-sensitive details',
            self::ResidentViewSensitive => 'View sensitive resident data including full NIK, contact, and identity docs',
            self::ResidentRegister => 'Register new prospective residents into a scope',
            self::ResidentVerify => 'Verify and activate prospective residents',
            self::ResidentUpdate => 'Update resident profile details',
            self::ResidentRelocate => 'Relocate residents internally or externally',
            self::ResidentRemoveFromHousehold => 'Remove residents from household membership',
            self::ResidentChangeStatus => 'Record resident civil status changes (including death)',

            self::HouseholdView => 'View households and membership compositions in a scope (KK number masked)',
            self::HouseholdViewSensitive => 'View the full Kartu Keluarga (KK) number of households in a scope',
            self::HouseholdCreate => 'Create new households in a scope',
            self::HouseholdUpdate => 'Update household address and dwelling details',
            self::HouseholdAddMember => 'Add residents to a household',
            self::HouseholdRemoveMember => 'Remove members from a household',
            self::HouseholdChangeHead => 'Appoint or change the head of household',
            self::HouseholdMove => 'Relocate a household internally or externally',
            self::HouseholdDissolve => 'Dissolve a household in a scope',

            self::FinanceAccountView => 'View cash accounts and financial summaries in a scope',
            self::FinanceAccountManage => 'Create, update, and close cash accounts in a scope',
            self::FinanceCategoryManage => 'Create and manage financial categories in a scope',
            self::FinanceTransactionView => 'View financial ledger transactions in a scope',
            self::FinanceTransactionRecord => 'Record income and expense transactions in a scope',
            self::FinanceTransactionReverse => 'Reverse posted financial transactions in a scope',
            self::FinancePeriodClose => 'Close monthly accounting periods and record reconciliation snapshots',
            self::FinanceTransferView => 'View internal cash transfers in a scope',
            self::FinanceTransferCreate => 'Record internal cash transfers between accounts in an authorized scope',
            self::FinanceTransferReverse => 'Reverse posted internal cash transfers in an authorized scope',

            self::FinanceBudgetView => 'View budget plans and lines in a scope',
            self::FinanceBudgetManage => 'Create and manage draft budget plans in a scope',
            self::FinanceBudgetPublish => 'Publish and amend budget plans in a scope',
            self::FinanceExpenditureView => 'View expenditure proposals and requests in a scope',
            self::FinanceExpenditureRequest => 'Submit expenditure proposals against budget lines in a scope',
            self::FinanceExpenditureApprove => 'Approve or reject expenditure proposals within authorized scope',
            self::FinanceExpenditurePost => 'Record approved expenditures into the immutable financial ledger',
        };
    }
}

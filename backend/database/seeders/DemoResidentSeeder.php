<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Household\Enums\HouseholdStatus;
use App\Domain\Household\Models\Household;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Enums\HouseholdMemberRelationship;
use App\Domain\Resident\Enums\ResidencyType;
use App\Domain\Resident\Enums\ResidentBloodType;
use App\Domain\Resident\Enums\ResidentEducation;
use App\Domain\Resident\Enums\ResidentGender;
use App\Domain\Resident\Enums\ResidentMaritalStatus;
use App\Domain\Resident\Enums\ResidentStatus;
use App\Domain\Resident\Models\HouseholdMember;
use App\Domain\Resident\Models\Resident;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoResidentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            /*
             * =============================================================
             * 1. ORGANIZATION & WILAYAH (RW / RT)
             * =============================================================
             */
            $organization = Organization::query()->firstOrCreate(
                ['slug' => 'demo-rtrw'],
                ['name' => 'Kompleks Demo RT/RW']
            );

            // RW 01
            $rw01 = Rw::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'number' => 1],
                ['name' => 'RW 01']
            );
            $rt01_01 = $this->findOrCreateRt($rw01, 1, 'RT 01');
            $rt01_02 = $this->findOrCreateRt($rw01, 2, 'RT 02');
            $rt01_03 = $this->findOrCreateRt($rw01, 3, 'RT 03');

            // RW 02
            $rw02 = Rw::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'number' => 2],
                ['name' => 'RW 02']
            );
            $rt02_01 = $this->findOrCreateRt($rw02, 1, 'RT 01');
            $rt02_02 = $this->findOrCreateRt($rw02, 2, 'RT 02');

            // RW 03
            $rw03 = Rw::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'number' => 3],
                ['name' => 'RW 03']
            );
            $rt03_01 = $this->findOrCreateRt($rw03, 1, 'RT 01');

            /*
             * =============================================================
             * 2. DATA RW 01 / RT 01
             * =============================================================
             */

            // KK 1: Keluarga Santoso (ACTIVE, 4 anggota)
            $kkSantoso = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                kkNumber: '3273010101200001',
                address: 'Jl. Melati No. 10, RT 01 / RW 01',
                status: HouseholdStatus::Active,
                notes: 'Keluarga Budi Santoso'
            );

            $budi = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273010101800001',
                name: 'Budi Santoso',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '1980-01-01',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Karyawan Swasta',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560001',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSantoso, $budi, HouseholdMemberRelationship::KepalaKeluarga);

            $siti = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273014202850002',
                name: 'Siti Aminah',
                gender: ResidentGender::Female,
                birthPlace: 'Jakarta',
                birthDate: '1985-02-02',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::A,
                occupation: 'Ibu Rumah Tangga',
                education: ResidentEducation::Sma,
                phone: '081234560002',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSantoso, $siti, HouseholdMemberRelationship::Istri);

            $andi = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273011505120003',
                name: 'Andi Santoso',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2012-05-15',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::O,
                occupation: 'Pelajar',
                education: ResidentEducation::Smp,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSantoso, $andi, HouseholdMemberRelationship::Anak);

            $nisa = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273012507150004',
                name: 'Nisa Santoso',
                gender: ResidentGender::Female,
                birthPlace: 'Bekasi',
                birthDate: '2015-07-25',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::A,
                occupation: 'Pelajar',
                education: ResidentEducation::Sd,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSantoso, $nisa, HouseholdMemberRelationship::Anak);

            // KK 2: Keluarga Gunawan (ACTIVE, Multi-generasi dengan Lansia)
            $kkGunawan = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                kkNumber: '3273010101200002',
                address: 'Jl. Melati No. 12, RT 01 / RW 01',
                status: HouseholdStatus::Active,
                notes: 'Keluarga Hendra Gunawan'
            );

            $hendraG = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273011003780005',
                name: 'Hendra Gunawan',
                gender: ResidentGender::Male,
                birthPlace: 'Bandung',
                birthDate: '1978-03-10',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::B,
                occupation: 'Arsitek',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560005',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkGunawan, $hendraG, HouseholdMemberRelationship::KepalaKeluarga);

            $maria = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273015509820006',
                name: 'dr. Maria Fransiska',
                gender: ResidentGender::Female,
                birthPlace: 'Surabaya',
                birthDate: '1982-09-15',
                religion: 'KATOLIK',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::Ab,
                occupation: 'Dokter Umum',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560006',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkGunawan, $maria, HouseholdMemberRelationship::Istri);

            $kevin = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273012010100007',
                name: 'Kevin Gunawan',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2010-10-20',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Pelajar',
                education: ResidentEducation::Sma,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkGunawan, $kevin, HouseholdMemberRelationship::Anak);

            $yohanes = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273010504500008',
                name: 'Yohanes Gunawan',
                gender: ResidentGender::Male,
                birthPlace: 'Semarang',
                birthDate: '1950-04-05',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Widowed,
                bloodType: ResidentBloodType::O,
                occupation: 'Pensiunan',
                education: ResidentEducation::Diploma,
                phone: '081234560008',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkGunawan, $yohanes, HouseholdMemberRelationship::OrangTua);

            // KK 3: KK Ibu Ratna (HEAD_VACANT — Kepala Keluarga meninggal, menguji UI kosong)
            $kkRatna = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                kkNumber: '3273010101200003',
                address: 'Jl. Melati No. 15, RT 01 / RW 01',
                status: HouseholdStatus::HeadVacant,
                notes: 'Kepala keluarga telah meninggal dunia (Alm. Bambang Sutrisno)'
            );

            // Almarhum mantan kepala keluarga (DECEASED, historical member)
            $almBambang = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273011204720009',
                name: 'Alm. Bambang Sutrisno',
                gender: ResidentGender::Male,
                birthPlace: 'Solo',
                birthDate: '1972-04-12',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Karyawan Swasta',
                education: ResidentEducation::SarjanaS1,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Deceased,
            );
            $this->addHistoricalMember(
                household: $kkRatna,
                resident: $almBambang,
                relationship: HouseholdMemberRelationship::KepalaKeluarga,
                joinedAt: '2015-01-01',
                leftAt: '2024-06-10',
                leftReason: 'Meninggal dunia'
            );

            $ratna = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273015206760010',
                name: 'Ratna Juwita',
                gender: ResidentGender::Female,
                birthPlace: 'Yogyakarta',
                birthDate: '1976-06-12',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Widowed,
                bloodType: ResidentBloodType::A,
                occupation: 'Guru SMP',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560010',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkRatna, $ratna, HouseholdMemberRelationship::Istri);

            $dimasS = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273010808050011',
                name: 'Dimas Sutrisno',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2005-08-08',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::O,
                occupation: 'Mahasiswa',
                education: ResidentEducation::Sma,
                phone: '081234560011',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkRatna, $dimasS, HouseholdMemberRelationship::Anak);

            // Warga tanpa KK di RT 01 (Anak Kos / Pendatang / Calon Warga)
            $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273011402980012',
                name: 'Aditya Pratama',
                gender: ResidentGender::Male,
                birthPlace: 'Palembang',
                birthDate: '1998-02-14',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Software Engineer',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560012',
                residencyType: ResidencyType::Temporary,
                status: ResidentStatus::Active,
            );

            $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_01,
                nik: '3273011905040013',
                name: 'Farhan Hakim',
                gender: ResidentGender::Male,
                birthPlace: 'Padang',
                birthDate: '2004-05-19',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::A,
                occupation: 'Mahasiswa',
                education: ResidentEducation::Sma,
                phone: '081234560013',
                residencyType: ResidencyType::Temporary,
                status: ResidentStatus::Prospective, // Menguji status PROSPECTIVE
            );

            /*
             * =============================================================
             * 3. DATA RW 01 / RT 02
             * =============================================================
             */

            // KK 4: Keluarga Fauzi (ACTIVE)
            $kkFauzi = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                kkNumber: '3273010102200004',
                address: 'Jl. Mawar No. 05, RT 02 / RW 01',
                status: HouseholdStatus::Active,
                notes: 'Keluarga Ahmad Fauzi'
            );

            $ahmad = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273011003750014',
                name: 'Ahmad Fauzi',
                gender: ResidentGender::Male,
                birthPlace: 'Depok',
                birthDate: '1975-03-10',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::B,
                occupation: 'Wiraswasta',
                education: ResidentEducation::Sma,
                phone: '081234560014',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkFauzi, $ahmad, HouseholdMemberRelationship::KepalaKeluarga);

            $dewi = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273015204800015',
                name: 'Dewi Lestari',
                gender: ResidentGender::Female,
                birthPlace: 'Jakarta',
                birthDate: '1980-04-12',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Guru SD',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560015',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkFauzi, $dewi, HouseholdMemberRelationship::Istri);

            $raka = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273011809100016',
                name: 'Raka Fauzi',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2010-09-18',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Pelajar',
                education: ResidentEducation::Sma,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkFauzi, $raka, HouseholdMemberRelationship::Anak);

            $nurhayati = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273014101550017',
                name: 'Nurhayati',
                gender: ResidentGender::Female,
                birthPlace: 'Depok',
                birthDate: '1955-01-01',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Widowed,
                bloodType: ResidentBloodType::B,
                occupation: 'Tidak Bekerja',
                education: ResidentEducation::Sd,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkFauzi, $nurhayati, HouseholdMemberRelationship::Mertua);

            // KK 5: Pak Joko (KK Tunggal / Single person)
            $kkJoko = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                kkNumber: '3273010102200005',
                address: 'Jl. Mawar No. 08, RT 02 / RW 01',
                status: HouseholdStatus::Active,
                notes: 'KK Tunggal - Pensiunan tinggal sendiri'
            );

            $joko = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273011508600018',
                name: 'Ir. Joko Widodo Kusumo',
                gender: ResidentGender::Male,
                birthPlace: 'Solo',
                birthDate: '1960-08-15',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Widowed,
                bloodType: ResidentBloodType::O,
                occupation: 'Pensiunan BUMN',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560018',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkJoko, $joko, HouseholdMemberRelationship::KepalaKeluarga);

            // KK 6: KK Pindah (MOVED — seluruh keluarga sudah pindah)
            $kkPindah = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                kkNumber: '3273010102200006',
                address: 'Jl. Mawar No. 20, RT 02 / RW 01',
                status: HouseholdStatus::Moved,
                notes: 'Keluarga pindah domisili ke Jawa Timur'
            );

            $herman = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273011010700019',
                name: 'Herman Susilo',
                gender: ResidentGender::Male,
                birthPlace: 'Surabaya',
                birthDate: '1970-10-10',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::A,
                occupation: 'Wiraswasta',
                education: ResidentEducation::Sma,
                phone: '081234560019',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Moved,
            );
            $this->addHistoricalMember(
                household: $kkPindah,
                resident: $herman,
                relationship: HouseholdMemberRelationship::KepalaKeluarga,
                joinedAt: '2018-05-01',
                leftAt: '2025-12-01',
                leftReason: 'Pindah ke Surabaya'
            );

            $endang = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_02,
                nik: '3273015011740020',
                name: 'Endang Sri Wahyuni',
                gender: ResidentGender::Female,
                birthPlace: 'Malang',
                birthDate: '1974-11-10',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Ibu Rumah Tangga',
                education: ResidentEducation::Sma,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Moved,
            );
            $this->addHistoricalMember(
                household: $kkPindah,
                resident: $endang,
                relationship: HouseholdMemberRelationship::Istri,
                joinedAt: '2018-05-01',
                leftAt: '2025-12-01',
                leftReason: 'Pindah ke Surabaya'
            );

            /*
             * =============================================================
             * 4. DATA RW 01 / RT 03
             * =============================================================
             */

            // KK 7: Keluarga Tanuwijaya (ACTIVE)
            $kkTanu = $this->createHousehold(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_03,
                kkNumber: '3273010103200007',
                address: 'Jl. Anggrek No. 03, RT 03 / RW 01',
                status: HouseholdStatus::Active,
                notes: 'Keluarga David Tanuwijaya'
            );

            $david = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_03,
                nik: '3273012202850021',
                name: 'David Tanuwijaya',
                gender: ResidentGender::Male,
                birthPlace: 'Medan',
                birthDate: '1985-02-22',
                religion: 'BUDDHA',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::A,
                occupation: 'Pengusaha Tekstil',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560021',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkTanu, $david, HouseholdMemberRelationship::KepalaKeluarga);

            $christine = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_03,
                nik: '3273015807880022',
                name: 'Christine Wijaya',
                gender: ResidentGender::Female,
                birthPlace: 'Jakarta',
                birthDate: '1988-07-18',
                religion: 'BUDDHA',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::Ab,
                occupation: 'Desainer Grafis',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560022',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkTanu, $christine, HouseholdMemberRelationship::Istri);

            $celine = $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_03,
                nik: '3273016012180023',
                name: 'Celine Tanuwijaya',
                gender: ResidentGender::Female,
                birthPlace: 'Bekasi',
                birthDate: '2018-12-20',
                religion: 'BUDDHA',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::A,
                occupation: 'Belum Bekerja',
                education: ResidentEducation::None,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkTanu, $celine, HouseholdMemberRelationship::Anak);

            // Warga tanpa KK di RT 03
            $this->createResident(
                organization: $organization,
                rw: $rw01,
                rt: $rt01_03,
                nik: '3273014506990024',
                name: 'Maya Indah',
                gender: ResidentGender::Female,
                birthPlace: 'Bogor',
                birthDate: '1999-06-05',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Bidan',
                education: ResidentEducation::Diploma,
                phone: '081234560024',
                residencyType: ResidencyType::Temporary,
                status: ResidentStatus::Active,
            );

            /*
             * =============================================================
             * 5. DATA RW 02 / RT 01
             * =============================================================
             */

            // KK 8: Keluarga Siregar (ACTIVE, 5 anggota dengan Famili Lain)
            $kkSiregar = $this->createHousehold(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                kkNumber: '3273010201200008',
                address: 'Jl. Flamboyan No. 01, RT 01 / RW 02',
                status: HouseholdStatus::Active,
                notes: 'Keluarga Sahat Siregar'
            );

            $sahat = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                nik: '3273011901700025',
                name: 'Sahat Siregar, S.H., M.H.',
                gender: ResidentGender::Male,
                birthPlace: 'Medan',
                birthDate: '1970-01-19',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Pengacara',
                education: ResidentEducation::MagisterS2,
                phone: '081234560025',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSiregar, $sahat, HouseholdMemberRelationship::KepalaKeluarga);

            $tiurma = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                nik: '3273015301750026',
                name: 'Tiurma Panjaitan, S.H., M.Kn.',
                gender: ResidentGender::Female,
                birthPlace: 'Pematang Siantar',
                birthDate: '1975-01-13',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::A,
                occupation: 'Notaris',
                education: ResidentEducation::MagisterS2,
                phone: '081234560026',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSiregar, $tiurma, HouseholdMemberRelationship::Istri);

            $daniel = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                nik: '3273011204010027',
                name: 'Daniel Siregar',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2001-04-12',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::O,
                occupation: 'Mahasiswa Hukum',
                education: ResidentEducation::Sma,
                phone: '081234560027',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSiregar, $daniel, HouseholdMemberRelationship::Anak);

            $grace = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                nik: '3273016408080028',
                name: 'Grace Siregar',
                gender: ResidentGender::Female,
                birthPlace: 'Bekasi',
                birthDate: '2008-08-24',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::A,
                occupation: 'Pelajar',
                education: ResidentEducation::Smp,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSiregar, $grace, HouseholdMemberRelationship::Anak);

            $martua = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_01,
                nik: '3273011503950029',
                name: 'Martua Siregar',
                gender: ResidentGender::Male,
                birthPlace: 'Medan',
                birthDate: '1995-03-15',
                religion: 'KRISTEN',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::O,
                occupation: 'Asisten Kantor',
                education: ResidentEducation::Diploma,
                phone: '081234560029',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSiregar, $martua, HouseholdMemberRelationship::FamiliLain);

            /*
             * =============================================================
             * 6. DATA RW 02 / RT 02
             * =============================================================
             */

            // KK 9: Keluarga I Wayan (ACTIVE)
            $kkWayan = $this->createHousehold(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                kkNumber: '3273010202200009',
                address: 'Jl. Kenanga No. 14, RT 02 / RW 02',
                status: HouseholdStatus::Active,
                notes: 'Keluarga I Wayan Sudarta'
            );

            $wayan = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                nik: '3273010808800030',
                name: 'Dr. I Wayan Sudarta, S.T., M.T.',
                gender: ResidentGender::Male,
                birthPlace: 'Denpasar',
                birthDate: '1980-08-08',
                religion: 'HINDU',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::B,
                occupation: 'Dosen Teknik',
                education: ResidentEducation::DoktorS3,
                phone: '081234560030',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkWayan, $wayan, HouseholdMemberRelationship::KepalaKeluarga);

            $sukerti = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                nik: '3273014810840031',
                name: 'Ni Made Sukerti, S.E., Ak.',
                gender: ResidentGender::Female,
                birthPlace: 'Badung',
                birthDate: '1984-10-08',
                religion: 'HINDU',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::O,
                occupation: 'Akuntan Publik',
                education: ResidentEducation::SarjanaS1,
                phone: '081234560031',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkWayan, $sukerti, HouseholdMemberRelationship::Istri);

            $aryawan = $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                nik: '3273011405140032',
                name: 'I Putu Aryawan',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2014-05-14',
                religion: 'HINDU',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Pelajar',
                education: ResidentEducation::Sd,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkWayan, $aryawan, HouseholdMemberRelationship::Anak);

            // Warga lain di RT 02 (Status INACTIVE dan Non-KK)
            $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                nik: '3273010602900033',
                name: 'Toni Saputra',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '1990-02-06',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::B,
                occupation: 'Freelance Designer',
                education: ResidentEducation::Diploma,
                phone: '081234560033',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );

            $this->createResident(
                organization: $organization,
                rw: $rw02,
                rt: $rt02_02,
                nik: '3273014101880034',
                name: 'Siti Rohmah',
                gender: ResidentGender::Female,
                birthPlace: 'Cirebon',
                birthDate: '1988-01-01',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Divorced,
                bloodType: ResidentBloodType::O,
                occupation: 'Penjahit',
                education: ResidentEducation::Smp,
                phone: '081234560034',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Inactive, // Menguji status INACTIVE
            );

            /*
             * =============================================================
             * 7. DATA RW 03 / RT 01
             * =============================================================
             */

            // KK 10: Keluarga Salim (ACTIVE)
            $kkSalim = $this->createHousehold(
                organization: $organization,
                rw: $rw03,
                rt: $rt03_01,
                kkNumber: '3273010301200010',
                address: 'Jl. Bougenville No. 22, RT 01 / RW 03',
                status: HouseholdStatus::Active,
                notes: 'Keluarga Hendri Salim'
            );

            $hendri = $this->createResident(
                organization: $organization,
                rw: $rw03,
                rt: $rt03_01,
                nik: '3273011111810035',
                name: 'Hendri Salim',
                gender: ResidentGender::Male,
                birthPlace: 'Semarang',
                birthDate: '1981-11-11',
                religion: 'KONGHUCU',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::Ab,
                occupation: 'Pedagang Elektronik',
                education: ResidentEducation::Sma,
                phone: '081234560035',
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSalim, $hendri, HouseholdMemberRelationship::KepalaKeluarga);

            $meiling = $this->createResident(
                organization: $organization,
                rw: $rw03,
                rt: $rt03_01,
                nik: '3273015112850036',
                name: 'Mei Ling',
                gender: ResidentGender::Female,
                birthPlace: 'Jakarta',
                birthDate: '1985-12-11',
                religion: 'KONGHUCU',
                maritalStatus: ResidentMaritalStatus::Married,
                bloodType: ResidentBloodType::B,
                occupation: 'Ibu Rumah Tangga',
                education: ResidentEducation::Sma,
                residencyType: ResidencyType::Permanent,
                status: ResidentStatus::Active,
            );
            $this->addMember($kkSalim, $meiling, HouseholdMemberRelationship::Istri);

            // Warga PROSPECTIVE di RW 03
            $this->createResident(
                organization: $organization,
                rw: $rw03,
                rt: $rt03_01,
                nik: '3273011206060037',
                name: 'Dimas Ramadhan',
                gender: ResidentGender::Male,
                birthPlace: 'Bekasi',
                birthDate: '2006-06-12',
                religion: 'ISLAM',
                maritalStatus: ResidentMaritalStatus::Single,
                bloodType: ResidentBloodType::O,
                occupation: 'Mahasiswa',
                education: ResidentEducation::Sma,
                residencyType: ResidencyType::Temporary,
                status: ResidentStatus::Prospective,
            );

            $this->command?->info('DemoResidentSeeder: 3 RW, 6 RT, 10 Kartu Keluarga, dan 37 Warga berhasil dibuat.');
        });
    }

    private function findOrCreateRt(Rw $rw, int $number, string $name): Rt
    {
        return Rt::query()->firstOrCreate(
            ['rw_id' => $rw->id, 'number' => $number],
            ['name' => $name]
        );
    }

    private function createHousehold(
        Organization $organization,
        Rw $rw,
        Rt $rt,
        string $kkNumber,
        string $address,
        HouseholdStatus $status = HouseholdStatus::Active,
        ?string $notes = null,
    ): Household {
        return Household::query()->updateOrCreate(
            ['kk_number' => $kkNumber],
            [
                'organization_id' => $organization->id,
                'rw_id' => $rw->id,
                'rt_id' => $rt->id,
                'address' => $address,
                'status' => $status,
                'notes' => $notes,
            ]
        );
    }

    private function createResident(
        Organization $organization,
        Rw $rw,
        Rt $rt,
        string $nik,
        string $name,
        ResidentGender $gender,
        string $birthPlace,
        string $birthDate,
        string $religion,
        ResidentMaritalStatus $maritalStatus,
        ResidentBloodType $bloodType,
        string $occupation,
        ResidentEducation $education,
        ?string $phone = null,
        ResidencyType $residencyType = ResidencyType::Permanent,
        ResidentStatus $status = ResidentStatus::Active,
    ): Resident {
        return Resident::query()->updateOrCreate(
            ['nik' => $nik],
            [
                'organization_id' => $organization->id,
                'rw_id' => $rw->id,
                'rt_id' => $rt->id,
                'user_id' => null, // Data murni domain — tanpa akun login
                'current_household_id' => null, // Diset via addMember jika ada KK
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $birthPlace,
                'birth_date' => $birthDate,
                'religion' => $religion,
                'marital_status' => $maritalStatus,
                'blood_type' => $bloodType,
                'occupation' => $occupation,
                'education' => $education,
                'phone' => $phone,
                'residency_type' => $residencyType,
                'status' => $status,
            ]
        );
    }

    private function addMember(
        Household $household,
        Resident $resident,
        HouseholdMemberRelationship $relationship,
        string $joinedAt = '2020-01-01',
    ): HouseholdMember {
        // Update current_household_id resident terlebih dahulu agar sinkron
        if ($resident->current_household_id !== $household->id) {
            $resident->forceFill([
                'current_household_id' => $household->id,
            ])->save();
        }

        return HouseholdMember::query()->updateOrCreate(
            [
                'household_id' => $household->id,
                'resident_id' => $resident->id,
                'is_active' => true,
            ],
            [
                'relationship' => $relationship,
                'joined_at' => $joinedAt,
                'left_at' => null,
                'left_reason' => null,
            ]
        );
    }

    private function addHistoricalMember(
        Household $household,
        Resident $resident,
        HouseholdMemberRelationship $relationship,
        string $joinedAt,
        string $leftAt,
        string $leftReason,
    ): HouseholdMember {
        // Resident yang sudah tidak aktif di KK tidak memiliki current_household_id ke KK tersebut
        if ($resident->current_household_id === $household->id) {
            $resident->forceFill([
                'current_household_id' => null,
            ])->save();
        }

        return HouseholdMember::query()->updateOrCreate(
            [
                'household_id' => $household->id,
                'resident_id' => $resident->id,
                'is_active' => false,
            ],
            [
                'relationship' => $relationship,
                'joined_at' => $joinedAt,
                'left_at' => $leftAt,
                'left_reason' => $leftReason,
            ]
        );
    }
}

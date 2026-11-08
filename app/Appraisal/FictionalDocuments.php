<?php

namespace App\Appraisal;

use Dompdf\Dompdf;
use Illuminate\Support\Str;

class FictionalDocuments
{
    public function generate(): string
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Fictional documents are local/testing only');
        $directory = storage_path('app/fictional-review-'.Str::uuid());
        mkdir($directory, 0700, true);
        $one = ['classification | | appraisal', 'form | | 1004', 'form_version | | 2005', 'address | | 1 FICTIONAL Example Street', 'parcel | | FICTIONAL PARCEL A', 'units | | 1', 'appraised_value | | 330000.00', 'contract_price | | 330000.00', 'inspection_date | | 2026-10-01', 'report_date | | 2026-10-02', 'condition | | Reported maintained, manual verification required', 'comparables | | FICTIONAL reported comparable explanations only', 'reconciliation | | No independent valuation performed', 'signature_presence | | Fictional visible marks only, not authenticated', 'certification | | Reported present, not certification of this synthetic document'];
        $multi = array_map(fn ($line) => $line === 'form | | 1004' ? 'form | | 1025' : ($line === 'units | | 1' ? 'units | | 4' : $line), $one);
        for ($u = 1; $u <= 4; $u++) {
            $multi[] = 'market_rent | '.$u.' | 825.00';
            $multi[] = 'inspection_occupancy | '.$u.' | unknown';
        }
        $files = ['FICTIONAL-primary-purchase.pdf' => $one, 'FICTIONAL-four-unit-appraisal.pdf' => $multi];
        foreach ([1, 2, 3] as $unit) {
            $files['FICTIONAL-lease-unit-'.$unit.'.pdf'] = ['classification | | lease', 'lease.address | | 1 FICTIONAL Example Street', 'lease.unit | | '.$unit, 'lease.parties | '.$unit.' | FICTIONAL landlord and tenant', 'lease.rent | '.$unit.' | 950.00', 'lease.start | '.$unit.' | 2026-01-01', 'lease.end | '.$unit.' | 2027-01-01', 'lease.signature_presence | '.$unit.' | Fictional visible marks, not authentic legal signatures', 'lease.continuation | '.$unit.' | No renewal assumed', 'lease.utilities | '.$unit.' | Reviewer must verify responsibilities'];
        }
        $files['FICTIONAL-lease-unit-3-amendment.pdf'] = ['classification | | lease_amendment', 'lease.address | | 1 FICTIONAL Example Street', 'lease.unit | | 3', 'lease.rent | 3 | 1000.00', 'lease.amendments | 3 | Proposed fictional rent change; original lease preserved'];
        $files['FICTIONAL-revised-appraisal.pdf'] = array_map(fn ($line) => $line === 'appraised_value | | 330000.00' ? 'appraised_value | | 340000.00' : $line, $multi);
        $files['FICTIONAL-bank-one-month.pdf'] = ['Institution: FICTIONAL Test Bank', 'Holder: FICTIONAL Example LLC', 'Account: ****1111', 'Currency: USD', 'Designation: business', 'Period start: 2025-01-01', 'Period end: 2025-01-31', 'Opening balance: 10000.00', 'Closing balance: 11000.00', 'Total credits: 1000.00', 'Total debits: 0.00', 'Fees: 0.00', 'Fees separate: no', 'Date | Description | Credit | Debit | Balance | Reference', '2025-01-02 | Merchant sales | 1000.00 | 0.00 | 11000.00 | TEST-M1'];
        foreach ($files as $name => $lines) {
            $pdf = new Dompdf;
            $html = '<html><body><h1>FICTIONAL DEVELOPMENT DOCUMENT</h1><p>Not a Fannie Mae form, actual appraisal, executed lease or bank statement. Labeled native-layout demonstration only. Never use for a real loan.</p>';
            foreach ($lines as $line) {
                $html .= '<p>'.htmlspecialchars($line).'</p>';
            }$pdf->loadHtml($html.'</body></html>');
            $pdf->render();
            file_put_contents($directory.'/'.$name, $pdf->output(), LOCK_EX);
            chmod($directory.'/'.$name, 0600);
        }

        return $directory;
    }
}

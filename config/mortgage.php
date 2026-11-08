<?php

return ['private_disk' => env('PRIVATE_DOCUMENT_DISK', 'private'), 'max_upload_kb' => 20480, 'analysis_enabled' => false, 'job_stages' => ['upload_validation', 'document_classification', 'extraction', 'reconciliation', 'transaction_classification', 'transfer_matching', 'income_calculation', 'integrity_analysis', 'appraisal_analysis', 'report_generation']];

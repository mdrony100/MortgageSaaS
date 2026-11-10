<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Original bytes stay append-only. The scan verdict is operational status and is written
        // after the asynchronous scan, including when that scan stops the run.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_document_version() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN
    RAISE EXCEPTION 'Evidence and audit rows are append-only';
  END IF;
  IF NEW.scan_status IS DISTINCT FROM OLD.scan_status
     AND NEW.id IS NOT DISTINCT FROM OLD.id
     AND NEW.organization_id IS NOT DISTINCT FROM OLD.organization_id
     AND NEW.document_id IS NOT DISTINCT FROM OLD.document_id
     AND NEW.uploaded_by IS NOT DISTINCT FROM OLD.uploaded_by
     AND NEW.version IS NOT DISTINCT FROM OLD.version
     AND NEW.disk IS NOT DISTINCT FROM OLD.disk
     AND NEW.storage_key IS NOT DISTINCT FROM OLD.storage_key
     AND NEW.original_name IS NOT DISTINCT FROM OLD.original_name
     AND NEW.mime IS NOT DISTINCT FROM OLD.mime
     AND NEW.bytes IS NOT DISTINCT FROM OLD.bytes
     AND NEW.sha256 IS NOT DISTINCT FROM OLD.sha256
     AND NEW.validation_status IS NOT DISTINCT FROM OLD.validation_status
     AND NEW.status IS NOT DISTINCT FROM OLD.status
     AND NEW.created_at IS NOT DISTINCT FROM OLD.created_at
     AND NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at
  THEN
    RETURN NEW;
  END IF;
  RAISE EXCEPTION 'Evidence and audit rows are append-only';
END $$;
DROP TRIGGER IF EXISTS immutable_document_versions ON document_versions;
CREATE TRIGGER document_versions_evidence BEFORE UPDATE OR DELETE ON document_versions FOR EACH ROW EXECUTE PROCEDURE protect_document_version();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS document_versions_evidence ON document_versions;
CREATE TRIGGER immutable_document_versions BEFORE UPDATE OR DELETE ON document_versions FOR EACH ROW EXECUTE PROCEDURE mortgage_immutable();
DROP FUNCTION IF EXISTS protect_document_version();
SQL);
    }
};

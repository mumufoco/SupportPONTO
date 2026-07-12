<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class ExpandConsentProcessingContext extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE user_consents ALTER COLUMN processing_context TYPE TEXT');
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE user_consents ALTER COLUMN processing_context TYPE VARCHAR(80) USING LEFT(processing_context,80)");
    }
}

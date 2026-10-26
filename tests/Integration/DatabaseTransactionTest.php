<?php

declare(strict_types=1);

namespace Tests\Integration;

use Exception;
use WTSBill\Core\Database;
use WTSBill\Core\DatabaseTransaction;
use WTSBill\Core\Session;
use WTSBill\Repositories\BaseRepository;

/**
 * Test Repository implementation for testing BaseRepository.
 */
class TagTestRepository extends BaseRepository
{
    protected string $table = 'tags';
    protected bool $tenantScoped = true;
}

/**
 * Integration Test for ACID Transactions, Nested Savepoints, and BaseRepository.
 */
class DatabaseTransactionTest
{
    public static function run(callable $assert): void
    {
        // 1. Transaction Rollback
        $initialTagCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];

        Database::beginTransaction();
        Database::insert('tags', [
            'company_id' => 1,
            'name' => 'TRANSACTION_ROLLBACK_TEST_TAG',
            'color' => '#FF0055',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Database::rollBack();

        $afterRollbackCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];
        $assert('DBTX-01', 'Transaction rollback restores exact table count', $initialTagCount === $afterRollbackCount);

        // 2. Transaction Commit
        Database::beginTransaction();
        $committedId = Database::insert('tags', [
            'company_id' => 1,
            'name' => 'TRANSACTION_COMMIT_TEST_TAG',
            'color' => '#00FF55',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Database::commit();

        $committedRecord = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $committedId]);
        $assert('DBTX-02', 'Transaction commit persists record', 
            $committedRecord !== null && $committedRecord['name'] === 'TRANSACTION_COMMIT_TEST_TAG');

        // Cleanup
        Database::delete('tags', 'id = :id', ['id' => $committedId]);

        // 3. Callback transaction() Commit
        $cbId = Database::transaction(function () {
            return Database::insert('tags', [
                'company_id' => 1,
                'name' => 'CB_COMMIT_TEST_TAG',
                'color' => '#0055FF',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        });

        $cbRecord = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $cbId]);
        $assert('DBTX-03', 'Database::transaction() callback commits on success', $cbRecord !== null);
        Database::delete('tags', 'id = :id', ['id' => $cbId]);

        // 4. Callback transaction() Rollback on Exception
        $beforeExceptionCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];
        $caughtException = false;

        try {
            Database::transaction(function () {
                Database::insert('tags', [
                    'company_id' => 1,
                    'name' => 'CB_FAIL_TEST_TAG',
                    'color' => '#FFAA00',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                throw new Exception('Simulated business validation failure during checkout');
            });
        } catch (\Throwable $e) {
            $caughtException = true;
        }

        $afterExceptionCount = (int) Database::selectOne("SELECT COUNT(*) as cnt FROM tags")['cnt'];
        $assert('DBTX-04', 'Exception thrown inside callback triggers automatic rollback', 
            $caughtException === true && $beforeExceptionCount === $afterExceptionCount);

        // 5. Nested Savepoint Testing
        $tx = new DatabaseTransaction(Database::pdo());
        $tx->begin(); // depth 1 (real transaction)
        $assert('DBTX-05', 'Outer transaction depth is 1', $tx->getDepth() === 1);

        $outerId = Database::insert('tags', [
            'company_id' => 1,
            'name' => 'OUTER_TX_TAG',
            'color' => '#111111',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $tx->begin(); // depth 2 (SAVEPOINT TRANS_POINT_2)
        $assert('DBTX-06', 'Inner transaction depth is 2 (Savepoint)', $tx->getDepth() === 2);

        $innerId = Database::insert('tags', [
            'company_id' => 1,
            'name' => 'INNER_FAIL_TAG',
            'color' => '#222222',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $tx->rollBack(); // rolls back to SAVEPOINT TRANS_POINT_2
        $assert('DBTX-07', 'Inner rollback returns depth to 1', $tx->getDepth() === 1);

        $tx->commit(); // commits outer transaction
        $assert('DBTX-08', 'Outer commit resets depth to 0', $tx->getDepth() === 0);

        // Verify: Outer record exists, Inner record does NOT exist
        $outerCheck = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $outerId]);
        $innerCheck = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $innerId]);

        $assert('DBTX-09', 'Outer record was successfully committed', $outerCheck !== null);
        $assert('DBTX-10', 'Inner record was rolled back via savepoint', $innerCheck === null);

        // Cleanup
        if ($outerCheck) {
            Database::delete('tags', 'id = :id', ['id' => $outerId]);
        }

        // 6. BaseRepository Tests
        Session::setTenantContext(1);
        $repo = new TagTestRepository();

        $newTagId = $repo->create([
            'name' => 'BASE_REPO_TAG',
            'color' => '#334455',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $assert('DBREPO-01', 'BaseRepository::create() inserts record and auto-applies company_id', 
            is_numeric($newTagId) && (int) $newTagId > 0);

        $found = $repo->find($newTagId);
        $assert('DBREPO-02', 'BaseRepository::find() locates record', 
            $found !== null && $found['name'] === 'BASE_REPO_TAG' && (int) $found['company_id'] === 1);

        $matched = $repo->whereOne(['name' => 'BASE_REPO_TAG']);
        $assert('DBREPO-03', 'BaseRepository::whereOne() finds exact match', $matched !== null && $matched['id'] == $newTagId);

        $updatedRows = $repo->update($newTagId, ['color' => '#998877']);
        $assert('DBREPO-04', 'BaseRepository::update() modifies row', $updatedRows === 1);

        $updatedRecord = $repo->find($newTagId);
        $assert('DBREPO-05', 'Updated row color verified', $updatedRecord['color'] === '#998877');

        $repoCount = $repo->count(['name' => 'BASE_REPO_TAG']);
        $assert('DBREPO-06', 'BaseRepository::count() returns correct aggregate', $repoCount === 1);

        $paged = $repo->paginate(1, 10, ['name' => 'BASE_REPO_TAG']);
        $assert('DBREPO-07', 'BaseRepository::paginate() returns envelope with total count', 
            $paged['total'] === 1 && count($paged['data']) === 1);

        $deletedRows = $repo->delete($newTagId);
        $assert('DBREPO-08', 'BaseRepository::delete() removes record', $deletedRows === 1);

        $afterDelete = $repo->find($newTagId);
        $assert('DBREPO-09', 'Deleted record no longer found', $afterDelete === null);
    }
}

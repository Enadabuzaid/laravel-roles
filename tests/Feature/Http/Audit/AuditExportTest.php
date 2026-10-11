<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Tests\Support\TestUser;

function exportBody($response): string
{
    return $response->streamedContent();
}

/** @return list<list<string>> */
function csvRows(string $body): array
{
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $body);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }

    return $rows;
}

function exporter(): TestUser
{
    return auditActor(['access.audit.view', 'access.reports.export'], 'exporter@example.test');
}

it('streams a CSV with the english headers and one row per entry', function (): void {
    $user = exporter();
    $target = makeUser('t@example.test');
    makeAuditEntry([
        'event' => 'users.suspended', 'severity' => 'warning', 'ip_address' => '10.0.0.9',
        'actor_type' => $user->getMorphClass(), 'actor_id' => $user->id, 'actor_label' => 'Exporter',
        'subject_type' => $target->getMorphClass(), 'subject_id' => $target->id, 'subject_label' => 'Target T',
        'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'suspended'], 'meta' => ['reason' => 'Spam'],
    ]);
    ActionLog::query()->where('event', '!=', 'users.suspended')->delete();

    $response = $this->actingAs($user)->get('/api/access/audit/export')->assertOk();
    $body = exportBody($response);
    $rows = csvRows($body);

    expect($body)->toStartWith("\xEF\xBB\xBF")
        ->and($rows[0])->toBe(['Time', 'Actor', 'Module', 'Action', 'Summary', 'Target', 'Severity', 'IP', 'Changes'])
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('Exporter')->and($rows[1][2])->toBe('users')->and($rows[1][3])->toBe('users.suspended')
        ->and($rows[1][4])->toBe('Exporter suspended Target T')->and($rows[1][5])->toBe('Target T')
        ->and($rows[1][6])->toBe('warning')->and($rows[1][7])->toBe('10.0.0.9')->and($rows[1][8])->toBe('status: active → suspended')
        ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toMatch('/^attachment; filename="audit-all-\d{8}-\d{4}\.csv"$/');
});

it('localizes the headers and summaries through the request locale', function (): void {
    $user = exporter();
    makeAuditEntry(['event' => 'permissions.synced', 'module' => 'permissions']);
    app()->setLocale('ar');

    $rows = csvRows(exportBody($this->actingAs($user)->get('/api/access/audit/export?filter[module]=permissions')));

    expect($rows[0])->toBe(['الوقت', 'المنفّذ', 'الوحدة', 'الإجراء', 'الملخص', 'الهدف', 'الخطورة', 'عنوان IP', 'التغييرات'])
        ->and($rows[1][4])->toContain('زامن');
    app()->setLocale('en');
});

it('exports exactly the filtered ids the list returns and names the module in the file', function (): void {
    $user = exporter();
    foreach (['users', 'roles', 'users', 'permissions'] as $i => $module) {
        makeAuditEntry(['module' => $module, 'event' => "{$module}.updated", 'severity' => $i === 2 ? 'critical' : 'info']);
    }
    $query = 'filter[module]=users&filter[severity]=critical';
    $listed = collect($this->actingAs($user)->getJson('/api/access/audit?per_page=100&'.$query)->json('data'));

    $response = $this->actingAs($user)->get('/api/access/audit/export?'.$query)->assertOk();
    $rows = csvRows(exportBody($response));

    expect(count($rows) - 1)->toBe($listed->count())->toBe(1)
        ->and($response->headers->get('Content-Disposition'))->toContain('audit-users-');
});

it('exports newest first', function (): void {
    $user = exporter();
    ActionLog::query()->delete();
    makeAuditEntry(['event' => 'users.updated', 'subject_label' => 'First']);
    makeAuditEntry(['event' => 'users.updated', 'subject_label' => 'Second']);

    $rows = csvRows(exportBody($this->actingAs($user)->get('/api/access/audit/export')));

    expect($rows[1][5])->toBe('Second')->and($rows[2][5])->toBe('First');
});

it('redacts secrets in the changes column', function (): void {
    $user = exporter();
    ActionLog::query()->delete();
    makeAuditEntry(['old_values' => ['password' => 'old-secret'], 'new_values' => ['password' => 'new-secret', 'two_factor_secret' => 'ABCDEF']]);

    $body = exportBody($this->actingAs($user)->get('/api/access/audit/export'));

    expect($body)->not->toContain('old-secret')->not->toContain('new-secret')->not->toContain('ABCDEF')->toContain('[redacted]');
});

it('neutralizes formula injection in labels and keeps Arabic', function (): void {
    $user = exporter();
    ActionLog::query()->delete();
    makeAuditEntry(['subject_label' => '=HYPERLINK("http://evil","x")', 'actor_label' => 'مدير النظام']);
    makeAuditEntry(['subject_label' => 'x', 'actor_label' => '=SUM(1+1)']);

    $rows = csvRows(exportBody($this->actingAs($user)->get('/api/access/audit/export')));

    expect($rows[2][5])->toBe("'=HYPERLINK(\"http://evil\",\"x\")")->and($rows[2][1])->toBe('مدير النظام')
        ->and($rows[1][1])->toBe("'=SUM(1+1)")->and($rows[1][4])->toStartWith("'=SUM(1+1)");
});

it('refuses exports above the cap with export.too_large and writes no entry', function (): void {
    config(['roles.audit.export_max_rows' => 3]);
    $user = exporter();
    ActionLog::query()->delete();
    foreach (range(1, 4) as $i) {
        makeAuditEntry();
    }

    $this->actingAs($user)->getJson('/api/access/audit/export')->assertStatus(422)
        ->assertJson(['success' => false, 'code' => 'export.too_large', 'errors' => ['count' => 4, 'max' => 3]]);

    expect(ActionLog::query()->where('event', 'audit.exported')->count())->toBe(0);
});

it('allows exactly the cap', function (): void {
    config(['roles.audit.export_max_rows' => 3]);
    $user = exporter();
    ActionLog::query()->delete();
    foreach (range(1, 3) as $i) {
        makeAuditEntry();
    }

    expect(csvRows(exportBody($this->actingAs($user)->get('/api/access/audit/export'))))->toHaveCount(4);
});

it('needs both audit.view and reports.export', function (): void {
    makeAuditEntry();

    $this->actingAs(auditActor(['access.audit.view'], 'a@example.test'))->getJson('/api/access/audit/export')->assertForbidden()->assertJson(['code' => 'forbidden']);
    $this->actingAs(auditActor(['access.reports.export'], 'b@example.test'))->getJson('/api/access/audit/export')->assertForbidden();

    expect(ActionLog::query()->where('event', 'audit.exported')->count())->toBe(0);
});

it('answers 401 for a guest', function (): void {
    $this->getJson('/api/access/audit/export')->assertUnauthorized();
});

it('writes exactly one audit.exported entry with the filters and row count, not the rows', function (): void {
    $user = exporter();
    ActionLog::query()->delete();
    makeAuditEntry(['module' => 'roles', 'event' => 'roles.created', 'new_values' => ['name' => 'Secret role name']]);
    makeAuditEntry(['module' => 'users']);

    $response = $this->actingAs($user)->get('/api/access/audit/export?filter[module]=roles&range=7d&search=role')->assertOk();
    $rows = csvRows(exportBody($response));

    $entry = ActionLog::query()->where('event', 'audit.exported')->sole();
    expect($entry->module)->toBe('audit')->and($entry->severity)->toBe('info')->and($entry->actor_id)->toBe($user->id)
        ->and($entry->meta['rows'])->toBe(count($rows) - 1)
        ->and($entry->meta['filters'])->toEqual(['modules' => ['roles'], 'range' => '7d', 'search' => 'role'])
        ->and(json_encode($entry->meta))->not->toContain('Secret role name');
});

it('does not include its own export entry in the file', function (): void {
    $user = exporter();
    ActionLog::query()->delete();
    makeAuditEntry();

    $rows = csvRows(exportBody($this->actingAs($user)->get('/api/access/audit/export')));

    expect($rows)->toHaveCount(2)->and(array_column($rows, 3))->not->toContain('audit.exported');
});

it('rejects write methods on the export route', function (): void {
    $user = exporter();

    $this->actingAs($user)->postJson('/api/access/audit/export')->assertStatus(405);
    $this->actingAs($user)->deleteJson('/api/access/audit/1')->assertStatus(405);
    $this->actingAs($user)->putJson('/api/access/audit')->assertStatus(405);
});

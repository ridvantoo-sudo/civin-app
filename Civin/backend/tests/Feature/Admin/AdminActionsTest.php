<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Actions\BanUser;
use App\Features\Admin\Actions\ModerateLiveMessage;
use App\Features\Admin\Actions\TerminateLiveRoom;
use App\Features\Admin\Actions\UnbanUser;
use App\Features\Admin\Support\AdminRole;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\Reports\Models\Report;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Filament\Resources\LiveRoomResource;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WithdrawalResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\CreatesAdminUsers;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use CreatesAdminUsers;
    use RefreshDatabase;

    public function test_ban_user_updates_status_revokes_tokens_and_writes_audit_log(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->createToken('mobile')->plainTextToken;

        $banned = app(BanUser::class)->execute($admin, $user, 'Abuse');

        $this->assertSame(User::STATUS_BANNED, $banned->status);
        $this->assertNotNull($banned->banned_at);
        $this->assertSame('Abuse', $banned->ban_reason);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('activity_log', [
            'description' => 'user.banned',
            'causer_id' => $admin->getKey(),
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_unban_user_restores_active_status(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $user = User::factory()->create([
            'status' => User::STATUS_BANNED,
            'banned_at' => now(),
            'ban_reason' => 'temp',
        ]);

        $restored = app(UnbanUser::class)->execute($admin, $user);

        $this->assertSame(User::STATUS_ACTIVE, $restored->status);
        $this->assertNull($restored->banned_at);
        $this->assertNull($restored->ban_reason);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'user.unbanned',
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_terminate_live_room_ends_stream_and_audits(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $host = User::factory()->create();
        $room = LiveRoom::factory()->create([
            'host_id' => $host->getKey(),
            'status' => 'live',
            'started_at' => now()->subMinute(),
            'ended_at' => null,
        ]);
        LiveSession::query()->create(['room_id' => $room->getKey(), 'peak_viewers' => 0]);

        $ended = app(TerminateLiveRoom::class)->execute($admin, $room);

        $this->assertSame('ended', $ended->status);
        $this->assertNotNull($ended->ended_at);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'live_room.terminated',
            'subject_id' => $room->getKey(),
        ]);
    }

    public function test_moderate_live_message_soft_deletes_and_audits(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $room = LiveRoom::factory()->create(['status' => 'live']);
        $message = LiveMessage::factory()->create([
            'room_id' => $room->getKey(),
            'type' => LiveMessage::TYPE_TEXT,
            'message' => 'spam content',
        ]);

        $deleted = app(ModerateLiveMessage::class)->execute($admin, $message);

        $this->assertSoftDeleted('live_messages', ['id' => $deleted->getKey()]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'live_message.deleted',
            'subject_id' => $message->getKey(),
        ]);
    }

    public function test_filament_ban_action_from_user_resource(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->actingAs($admin);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->callTableAction('ban', $user, data: ['reason' => 'Spam'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(User::STATUS_BANNED, $user->fresh()->status);
        $this->assertTrue(Activity::query()->where('description', 'user.banned')->exists());
    }

    public function test_filament_terminate_action_from_live_room_resource(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);
        $room = LiveRoom::factory()->create([
            'status' => 'live',
            'started_at' => now()->subMinute(),
        ]);
        LiveSession::query()->create(['room_id' => $room->getKey(), 'peak_viewers' => 0]);

        $this->actingAs($admin);

        Livewire::test(LiveRoomResource\Pages\ListLiveRooms::class)
            ->callTableAction('terminate', $room)
            ->assertHasNoTableActionErrors();

        $this->assertSame('ended', $room->fresh()->status);
    }

    public function test_filament_withdrawal_approve_action(): void
    {
        $admin = $this->makeAdmin(AdminRole::FINANCE_ADMIN);
        $user = User::factory()->withDiamonds(500)->create();
        $withdrawal = WithdrawRequest::factory()->create([
            'user_id' => $user->getKey(),
            'diamonds' => 100,
            'amount' => 50,
            'status' => WithdrawRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin);

        Livewire::test(WithdrawalResource\Pages\ListWithdrawals::class)
            ->callTableAction('approve', $withdrawal)
            ->assertHasNoTableActionErrors();

        $this->assertSame(WithdrawRequest::STATUS_APPROVED, $withdrawal->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'withdrawal.approved',
            'subject_id' => $withdrawal->getKey(),
        ]);
    }

    public function test_filament_report_review_action(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPPORT_ADMIN);
        $report = Report::factory()->create(['status' => 'pending']);

        $this->actingAs($admin);

        Livewire::test(ReportResource\Pages\ListReports::class)
            ->callTableAction('review', $report, data: [
                'status' => 'resolved',
                'notes' => 'Handled',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'report.reviewed',
            'subject_id' => $report->getKey(),
        ]);
    }

    public function test_user_is_two_factor_ready(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertTrue($user->twoFactorReady());
    }
}

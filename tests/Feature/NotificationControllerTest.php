<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_user_notifications(): void
    {
        $otherUser = User::factory()->create();

        $userNotification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => ['message' => 'あなた宛ての通知です'],
            'read_at' => null,
        ]);

        DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => ['message' => '他人宛ての通知です'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('notifications.index'));

        $response->assertStatus(200)
            ->assertViewIs('notifications.index')
            ->assertViewHas('notifications');

        // 自分宛ての通知のみ（1件）が取得されているか検証
        $viewNotifications = $response->viewData('notifications');
        $this->assertCount(1, $viewNotifications);
        $this->assertEquals($userNotification->id, $viewNotifications->first()->id);
    }

    public function test_read_allows_owner_to_mark_as_read(): void
    {
        $notificationId = Str::uuid()->toString();

        $notification = DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => (string) $this->user->id,
            'data' => ['message' => '自分宛ての通知'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('notifications.read', ['id' => $notificationId]));

        $response->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_prevents_user_from_marking_others_notification_as_read(): void
    {
        $otherUser = User::factory()->create();
        $notificationId = Str::uuid()->toString();

        // 他のユーザー宛ての通知を作成
        $otherNotification = DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => ['message' => '他人の通知'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('notifications.read', ['id' => $notificationId]));

        $response->assertStatus(403);

        $this->assertNull($otherNotification->fresh()->read_at);
    }
}

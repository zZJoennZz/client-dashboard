<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\MemberAnnouncement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class SuperAdminCommunicationController extends Controller
{
    public function index(): JsonResponse
    {
        $paginator = MemberAnnouncement::query()->with('creator.profile')->latest()->paginate(20);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MemberAnnouncement $announcement): array => $this->announcementData($announcement))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $announcement = MemberAnnouncement::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);
        AuditLog::record($request->user(), 'announcement.drafted', $announcement, [], [
            'subject' => $announcement->subject,
            'audience' => $announcement->audience,
        ]);

        return response()->json($this->announcementData($announcement), 201);
    }

    public function update(Request $request, MemberAnnouncement $announcement): JsonResponse
    {
        abort_unless($announcement->status === 'draft', 409, 'Only draft announcements can be edited.');
        $data = $this->validated($request);
        $old = ['subject' => $announcement->subject, 'audience' => $announcement->audience];
        $announcement->fill($data)->save();
        AuditLog::record($request->user(), 'announcement.updated', $announcement, $old, [
            'subject' => $announcement->subject,
            'audience' => $announcement->audience,
        ]);

        return response()->json($this->announcementData($announcement));
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_category' => ['nullable', 'string', 'max:100'],
            'membership_status' => ['nullable', 'string', 'max:60'],
        ]);

        return response()->json([
            'recipient_count' => $this->recipients($data)->count(),
            'audience' => $data,
            'eligibility' => 'Only regular accounts with an active business association and an explicit email-announcement opt-in are eligible.',
        ]);
    }

    public function send(Request $request, MemberAnnouncement $announcement): JsonResponse
    {
        abort_unless(in_array($announcement->status, ['draft', 'failed'], true), 409, 'This announcement cannot be sent again.');
        if (! $this->configuredMailTransport()) {
            return response()->json([
                'message' => 'Email delivery is not configured with a sending transport. The announcement remains a draft.',
            ], 422);
        }

        $recipients = $this->recipients($announcement->audience ?? []);
        $failedAddresses = $announcement->status === 'failed'
            ? ($announcement->failed_recipients ?? [])
            : [];
        $targets = $failedAddresses === []
            ? $recipients->get()
            : $recipients->whereIn('users.email', $failedAddresses)->get();

        $failed = [];
        $delivered = 0;
        foreach ($targets as $user) {
            try {
                Mail::raw($announcement->body, function ($message) use ($announcement, $user): void {
                    $message->to($user->email)->subject($announcement->subject);
                });
                $delivered++;
            } catch (Throwable $exception) {
                Log::warning('Member announcement delivery failed.', [
                    'announcement_id' => $announcement->id,
                    'recipient_id' => $user->id,
                    'exception' => $exception::class,
                ]);
                $failed[] = $user->email;
            }
        }

        DB::transaction(function () use ($request, $announcement, $targets, $delivered, $failed): void {
            $locked = MemberAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
            $locked->recipient_count = max($locked->recipient_count, $targets->count());
            $locked->delivered_count += $delivered;
            $locked->failed_count = count($failed);
            $locked->failed_recipients = $failed;
            $locked->status = $failed === [] ? 'sent' : 'failed';
            $locked->sent_at = $failed === [] ? now() : $locked->sent_at;
            $locked->save();

            AuditLog::record($request->user(), 'announcement.delivery_attempted', $locked, [], [
                'recipient_count' => $targets->count(),
                'delivered_count' => $delivered,
                'failed_count' => count($failed),
                'status' => $locked->status,
            ]);
        });

        return response()->json($this->announcementData($announcement->fresh()), $failed === [] ? 200 : 207);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', 'array'],
            'audience.business_category' => ['nullable', 'string', 'max:100'],
            'audience.membership_status' => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function recipients(array $audience): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_REGULAR)
            ->whereHas('profile', fn ($profiles) => $profiles->where('email_announcements_opt_in', true))
            ->whereHas('businesses', function ($businesses) use ($audience): void {
                $businesses->where('business_user.status', 'active')
                    ->whereNull('clients.deleted_at');
                if (! empty($audience['business_category'])) {
                    $businesses->where('clients.settings->category', $audience['business_category']);
                }
                if (! empty($audience['membership_status'])) {
                    $businesses->whereHas('memberships', fn ($memberships) => $memberships->where('status', $audience['membership_status']));
                }
            });

        return $query;
    }

    private function configuredMailTransport(): bool
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        if ($transport === 'failover') {
            return collect(config("mail.mailers.{$mailer}.mailers", []))
                ->contains(fn (string $fallback): bool => ! in_array(
                    config("mail.mailers.{$fallback}.transport"),
                    ['log', 'array', null],
                    true
                ));
        }

        return ! in_array($transport, ['log', 'array', null], true);
    }

    private function announcementData(MemberAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'subject' => $announcement->subject,
            'body' => $announcement->body,
            'audience' => $announcement->audience,
            'status' => $announcement->status,
            'recipient_count' => $announcement->recipient_count,
            'delivered_count' => $announcement->delivered_count,
            'failed_count' => $announcement->failed_count,
            'failed_recipients' => $announcement->failed_recipients ?? [],
            'created_by' => $announcement->creator?->profile?->full_name,
            'sent_at' => $announcement->sent_at?->toDateTimeString(),
        ];
    }
}

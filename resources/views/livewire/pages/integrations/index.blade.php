<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\NotificationChannel;

new
#[Layout('layouts.app')]
class extends Component {
    public $slackWebhookUrl = '';
    public $slackEnabled = false;
    public $emailEnabled = true;
    public $webhooksEnabled = false;
    
    public $showSlackForm = false;
    public $testingSlack = false;

    public function mount()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        
        if (!$team) {
            return;
        }

        $channel = NotificationChannel::where('team_id', $team->id)->first();
        
        if ($channel) {
            $this->slackWebhookUrl = $channel->slack_webhook_url ?? '';
            $this->slackEnabled = $channel->slack_enabled ?? false;
            $this->emailEnabled = $channel->email_enabled ?? true;
            $this->webhooksEnabled = $channel->webhooks_enabled ?? false;
        }
    }

    #[Computed]
    public function hasTeamPlan()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        
        if (!$team) {
            return false;
        }

        $billable = $team->paddle_subscription_id ? $team : $user;
        return in_array(($billable->billing_plan ?? 'free'), ['team', 'business'], true);
    }

    public function saveSlackIntegration()
    {
        if (!$this->hasTeamPlan()) {
            session()->flash('error', 'Slack integration is only available on Team or Business plans.');
            return;
        }

        $this->validate([
            'slackWebhookUrl' => 'required|url',
        ]);

        $user = auth()->user();
        $team = $user->currentTeam;

        NotificationChannel::updateOrCreate(
            ['team_id' => $team->id],
            [
                'slack_webhook_url' => $this->slackWebhookUrl,
                'slack_enabled' => true,
            ]
        );

        $this->slackEnabled = true;
        $this->showSlackForm = false;
        session()->flash('success', 'Slack integration configured successfully!');
    }

    public function toggleSlack()
    {
        if (!$this->hasTeamPlan()) {
            session()->flash('error', 'Slack integration is only available on Team plans.');
            return;
        }

        $user = auth()->user();
        $team = $user->currentTeam;

        $channel = NotificationChannel::where('team_id', $team->id)->first();
        
        if ($channel) {
            $newState = !$this->slackEnabled;
            $channel->update(['slack_enabled' => $newState]);
            $this->slackEnabled = $newState;
            
            session()->flash('success', 'Slack notifications ' . ($this->slackEnabled ? 'enabled' : 'disabled'));
        } else {
            session()->flash('error', 'Please configure Slack integration first.');
        }
    }

    public function toggleEmail()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if ($team) {
            $channel = NotificationChannel::where('team_id', $team->id)->first();
            
            if ($channel) {
                $channel->update(['email_enabled' => !$this->emailEnabled]);
                $this->emailEnabled = !$this->emailEnabled;
            } else {
                $channel = NotificationChannel::create([
                    'team_id' => $team->id,
                    'email_enabled' => !$this->emailEnabled
                ]);
                $this->emailEnabled = $channel->email_enabled;
            }
        } else {
            // For non-team users, just toggle the local state
            $this->emailEnabled = !$this->emailEnabled;
        }

        session()->flash('success', 'Email notifications ' . ($this->emailEnabled ? 'enabled' : 'disabled'));
    }

    public function testSlack()
    {
        if (!$this->slackEnabled || !$this->slackWebhookUrl) {
            session()->flash('error', 'Please configure Slack integration first.');
            return;
        }

        $this->testingSlack = true;

        try {
            // Send test notification
            $payload = [
                'text' => '✅ Test notification from Monitly',
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => '🧪 Test Notification',
                        ]
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "This is a test notification from *Monitly*.\n\nIf you're seeing this, your Slack integration is working correctly! 🎉"
                        ]
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => 'Sent at ' . now()->format('Y-m-d H:i:s')
                            ]
                        ]
                    ]
                ]
            ];

            $response = \Illuminate\Support\Facades\Http::post($this->slackWebhookUrl, $payload);

            if ($response->successful()) {
                session()->flash('success', 'Test notification sent successfully! Check your Slack channel.');
            } else {
                session()->flash('error', 'Failed to send test notification. Please check your webhook URL.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error sending test notification: ' . $e->getMessage());
        }

        $this->testingSlack = false;
    }

    public function removeSlack()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        $channel = NotificationChannel::where('team_id', $team->id)->first();
        
        if ($channel) {
            $channel->update([
                'slack_webhook_url' => null,
                'slack_enabled' => false,
            ]);
        }

        $this->slackWebhookUrl = '';
        $this->slackEnabled = false;
        $this->showSlackForm = false;

        session()->flash('success', 'Slack integration removed');
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <li class="flex items-center">
            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="ml-2 text-sm font-medium text-gray-700">Integrations</span>
        </li>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Integrations</h1>
            <p class="mt-2 text-sm text-gray-600">Connect Monitly with your favorite tools</p>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        @if (session('error'))
        <x-ui.alert type="danger" class="mb-6">
            {{ session('error') }}
        </x-ui.alert>
        @endif

        <!-- Upgrade Notice for Free/Pro Users -->
        @if(!$this->hasTeamPlan())
        <x-ui.alert type="info" class="mb-6">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-900">Team Plan Required</h3>
                    <p class="text-sm text-gray-700 mt-1">
                        Slack and Webhook integrations are available on Team plans. Email notifications are included in all plans.
                    </p>
                    <a href="{{ route('billing.index') }}" class="inline-flex items-center gap-2 mt-3 text-sm font-medium text-blue-600 hover:text-blue-700">
                        Upgrade to Team Plan
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </x-ui.alert>
        @endif

        <!-- Integrations Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Email Integration -->
            <div class="bg-white rounded-lg border-2 border-gray-200 p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="h-12 w-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <button 
                        wire:click="toggleEmail"
                        wire:loading.attr="disabled"
                        type="button"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 {{ $emailEnabled ? 'bg-emerald-600' : 'bg-gray-200' }}"
                        role="switch"
                        aria-checked="{{ $emailEnabled ? 'true' : 'false' }}"
                    >
                        <span class="sr-only">Toggle email notifications</span>
                        <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $emailEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 mb-2">Email Notifications</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Receive alerts when monitors go down or recover via email.
                </p>

                <div class="flex items-center gap-2">
                    @if($emailEnabled)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                        ✓ Active
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        Disabled
                    </span>
                    @endif
                    <span class="text-xs text-gray-500">• All plans</span>
                </div>
            </div>

            <!-- Slack Integration -->
            <div class="bg-white rounded-lg border-2 {{ $this->hasTeamPlan() ? 'border-gray-200' : 'border-gray-100 opacity-60' }} p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="h-12 w-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
                        </svg>
                    </div>
                    @if($this->hasTeamPlan())
                    <button 
                        wire:click="toggleSlack"
                        wire:loading.attr="disabled"
                        @disabled(!$slackWebhookUrl)
                        type="button"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-600 focus:ring-offset-2 {{ $slackEnabled ? 'bg-emerald-600' : 'bg-gray-200' }} {{ !$slackWebhookUrl ? 'opacity-50 cursor-not-allowed' : '' }}"
                        role="switch"
                        aria-checked="{{ $slackEnabled ? 'true' : 'false' }}"
                    >
                        <span class="sr-only">Toggle Slack notifications</span>
                        <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $slackEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                    @else
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-700">
                        Team Plan
                    </span>
                    @endif
                </div>

                <h3 class="text-lg font-semibold text-gray-900 mb-2">Slack</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Get real-time alerts in your Slack workspace when issues occur.
                </p>

                <div class="space-y-3">
                    @if($slackEnabled && $slackWebhookUrl)
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            ✓ Connected
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="testSlack" wire:loading.attr="disabled" class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            {{ $testingSlack ? 'Sending...' : 'Test' }}
                        </button>
                        <button wire:click="removeSlack" class="flex-1 px-3 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                            Remove
                        </button>
                    </div>
                    @elseif($this->hasTeamPlan())
                    <button 
                        wire:click="$set('showSlackForm', true)"
                        class="w-full px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 rounded-lg transition-all"
                    >
                        Connect Slack
                    </button>
                    @else
                    <div class="text-xs text-gray-500">
                        Available on Team plans
                    </div>
                    @endif
                </div>
            </div>

            <!-- Webhooks Integration -->
            <div class="bg-white rounded-lg border-2 {{ $this->hasTeamPlan() ? 'border-gray-200' : 'border-gray-100 opacity-60' }} p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="h-12 w-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                    </div>
                    @if(!$this->hasTeamPlan())
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-orange-100 text-orange-700">
                        Team Plan
                    </span>
                    @endif
                </div>

                <h3 class="text-lg font-semibold text-gray-900 mb-2">Custom Webhooks</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Send HTTP requests to your custom endpoints for advanced integrations.
                </p>

                <div class="space-y-3">
                    @if($this->hasTeamPlan())
                    <button class="w-full px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 rounded-lg transition-all">
                        Configure Webhooks
                    </button>
                    @else
                    <div class="text-xs text-gray-500">
                        Available on Team plans
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Slack Configuration Modal -->
        @if($showSlackForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ open: @entangle('showSlackForm') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="open = false"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Configure Slack Integration
                                </h3>
                                
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Slack Webhook URL
                                        </label>
                                        <input 
                                            type="url" 
                                            wire:model="slackWebhookUrl"
                                            placeholder="https://hooks.slack.com/services/..." 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        >
                                        @error('slackWebhookUrl')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-blue-900 mb-2">How to get your webhook URL:</h4>
                                        <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                                            <li>Go to <a href="https://api.slack.com/messaging/webhooks" target="_blank" class="underline">api.slack.com/messaging/webhooks</a></li>
                                            <li>Create an Incoming Webhook for your workspace</li>
                                            <li>Copy the webhook URL and paste it above</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                        <button 
                            wire:click="saveSlackIntegration"
                            type="button" 
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Save Integration
                        </button>
                        <button 
                            wire:click="$set('showSlackForm', false)"
                            type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Help Section -->
        <div class="mt-12">
            <x-ui.card class="bg-gradient-to-r from-emerald-50 to-blue-50 border-emerald-200">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Need help with integrations?</h3>
                        <p class="text-sm text-gray-700 mb-4">
                            Check out our documentation for detailed setup guides and troubleshooting tips.
                        </p>
                        <div class="flex gap-3">
                            <a href="https://docs.monitly.app/integrations" target="_blank" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-600 hover:text-emerald-700">
                                View Documentation
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                            <a href="mailto:support@monitly.app" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                                Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>

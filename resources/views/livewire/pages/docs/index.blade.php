<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public $activeSection = 'getting-started';

    public function setSection($section)
    {
        $this->activeSection = $section;
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <li class="flex items-center">
            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="ml-2 text-sm font-medium text-gray-700">Documentation</span>
        </li>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            
            <!-- Sidebar Navigation -->
            <aside class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1">
                    <a wire:click.prevent="setSection('getting-started')" href="#" 
                       class="{{ $activeSection === 'getting-started' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'getting-started' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                        </svg>
                        Getting Started
                    </a>

                    <a wire:click.prevent="setSection('monitors')" href="#"
                       class="{{ $activeSection === 'monitors' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'monitors' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                        </svg>
                        Monitors
                    </a>

                    <a wire:click.prevent="setSection('notifications')" href="#"
                       class="{{ $activeSection === 'notifications' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'notifications' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        Notifications
                    </a>

                    <a wire:click.prevent="setSection('integrations')" href="#"
                       class="{{ $activeSection === 'integrations' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'integrations' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Integrations
                    </a>

                    <a wire:click.prevent="setSection('plans')" href="#"
                       class="{{ $activeSection === 'plans' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'plans' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                        </svg>
                        Plans & Billing
                    </a>

                    <a wire:click.prevent="setSection('faq')" href="#"
                       class="{{ $activeSection === 'faq' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }} group flex items-center px-3 py-2 text-sm rounded-lg transition-colors">
                        <svg class="mr-3 h-5 w-5 {{ $activeSection === 'faq' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                        FAQ
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="lg:col-span-9">
                <div class="bg-white rounded-lg border border-gray-200 p-8">
                    
                    @if($activeSection === 'getting-started')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Getting Started with Monitly</h1>
                        
                        <p class="text-lg text-gray-600 mb-6">
                            Welcome to Monitly! This guide will help you get started with monitoring your websites and APIs.
                        </p>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">What is Monitly?</h2>
                        <p class="text-gray-700 mb-4">
                            Monitly is a powerful uptime monitoring service that helps you keep track of your websites and APIs. 
                            We check your URLs at regular intervals and alert you immediately when something goes wrong.
                        </p>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Quick Start</h2>
                        <ol class="space-y-4 list-decimal list-inside text-gray-700">
                            <li class="pl-2">
                                <strong>Create Your First Monitor</strong>
                                <p class="ml-6 mt-2 text-gray-600">
                                    Click on "Monitors" in the sidebar, then click "New Monitor". Enter your website URL and give it a name.
                                </p>
                            </li>
                            <li class="pl-2">
                                <strong>Configure Check Interval</strong>
                                <p class="ml-6 mt-2 text-gray-600">
                                    Choose how often we should check your site (15 minutes for Free, 10 minutes for Pro/Team, or 5 minutes with addon).
                                </p>
                            </li>
                            <li class="pl-2">
                                <strong>Set Up Notifications</strong>
                                <p class="ml-6 mt-2 text-gray-600">
                                    Go to "Integrations" to enable email alerts, Slack notifications, or custom webhooks.
                                </p>
                            </li>
                            <li class="pl-2">
                                <strong>Monitor Dashboard</strong>
                                <p class="ml-6 mt-2 text-gray-600">
                                    View real-time status, uptime percentage, and response times from your dashboard.
                                </p>
                            </li>
                        </ol>

                        <div class="mt-8 p-6 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <h3 class="text-lg font-semibold text-emerald-900 mb-2">💡 Pro Tip</h3>
                            <p class="text-emerald-800">
                                Start with your most critical services first. You can always add more monitors later as you get familiar with the platform.
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($activeSection === 'monitors')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Managing Monitors</h1>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Creating a Monitor</h2>
                        <p class="text-gray-700 mb-4">
                            To create a new monitor:
                        </p>
                        <ol class="space-y-2 list-decimal list-inside text-gray-700">
                            <li>Navigate to the Monitors page</li>
                            <li>Click "New Monitor"</li>
                            <li>Enter the URL you want to monitor</li>
                            <li>Give it a descriptive name</li>
                            <li>Choose your check interval</li>
                            <li>Click "Create Monitor"</li>
                        </ol>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Monitor Settings</h2>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">URL</h3>
                                <p class="text-gray-700">
                                    The full URL to monitor (must include http:// or https://).
                                </p>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Check Interval</h3>
                                <p class="text-gray-700">
                                    How often we check your URL:
                                </p>
                                <ul class="list-disc list-inside ml-4 mt-2 text-gray-600">
                                    <li>Free Plan: 15 minutes</li>
                                    <li>Pro/Team Plan: 10 minutes</li>
                                    <li>With Faster Checks addon: 5 minutes</li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Status Codes</h3>
                                <p class="text-gray-700">
                                    We consider your site "up" if it returns a 2xx or 3xx status code. Any other response (4xx, 5xx) or timeout is considered "down".
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 p-6 bg-blue-50 border border-blue-200 rounded-lg">
                            <h3 class="text-lg font-semibold text-blue-900 mb-2">📊 Understanding Uptime</h3>
                            <p class="text-blue-800">
                                Uptime percentage is calculated as: (successful checks / total checks) × 100. A 99.9% uptime means your site was down for less than 44 minutes per month.
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($activeSection === 'notifications')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Notifications</h1>

                        <p class="text-lg text-gray-600 mb-6">
                            Get instant alerts when your monitors go down or recover.
                        </p>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Email Notifications</h2>
                        <p class="text-gray-700 mb-4">
                            Email notifications are included in all plans. You'll receive:
                        </p>
                        <ul class="list-disc list-inside space-y-2 text-gray-700 mb-6">
                            <li>Alert when a monitor goes down</li>
                            <li>Notification when it recovers</li>
                            <li>Details about downtime duration</li>
                            <li>Status code and response information</li>
                        </ul>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-gray-900 mb-2">Sample Email:</h4>
                            <div class="bg-white border border-red-200 rounded p-4">
                                <p class="text-red-600 font-bold mb-2">🚨 Monitor Down: api.example.com</p>
                                <p class="text-sm text-gray-700">Your monitor is currently down.</p>
                                <p class="text-sm text-gray-600 mt-2">Started: Jan 28, 2026 10:30:00</p>
                            </div>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Slack Notifications</h2>
                        <p class="text-gray-700 mb-4">
                            Available on Team plans. Receive real-time alerts in your Slack workspace.
                        </p>
                        <p class="text-gray-700 mb-4">
                            To set up Slack:
                        </p>
                        <ol class="list-decimal list-inside space-y-2 text-gray-700 mb-6">
                            <li>Go to Integrations page</li>
                            <li>Click "Connect Slack"</li>
                            <li>Create an Incoming Webhook in Slack</li>
                            <li>Paste the webhook URL</li>
                            <li>Click "Save Integration"</li>
                        </ol>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Custom Webhooks</h2>
                        <p class="text-gray-700 mb-4">
                            Available on Team plans. Send HTTP POST requests to your custom endpoints when incidents occur.
                        </p>
                        <p class="text-gray-700">
                            Webhook payload includes:
                        </p>
                        <ul class="list-disc list-inside space-y-2 text-gray-700">
                            <li>Monitor ID and name</li>
                            <li>URL being monitored</li>
                            <li>Status (down/recovered)</li>
                            <li>Timestamp</li>
                            <li>Downtime duration</li>
                        </ul>
                    </div>
                    @endif

                    @if($activeSection === 'integrations')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Integrations</h1>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Available Integrations</h2>

                        <div class="space-y-6">
                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Email</h3>
                                        <p class="text-sm text-gray-600">All plans</p>
                                    </div>
                                </div>
                                <p class="text-gray-700">
                                    Receive email alerts for all monitor events. Enabled by default.
                                </p>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-purple-600" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Slack</h3>
                                        <p class="text-sm text-gray-600">Team plan required</p>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-4">
                                    Get real-time notifications in your Slack channels.
                                </p>
                                <a href="{{ route('integrations.index') }}" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">
                                    Configure Slack →
                                </a>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="h-10 w-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Webhooks</h3>
                                        <p class="text-sm text-gray-600">Team plan required</p>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-4">
                                    Send HTTP POST requests to your custom endpoints.
                                </p>
                                <a href="{{ route('integrations.index') }}" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">
                                    Configure Webhooks →
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($activeSection === 'plans')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Plans & Billing</h1>

                        <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">Available Plans</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 not-prose">
                            <div class="border border-gray-200 rounded-lg p-6">
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Free</h3>
                                <p class="text-3xl font-bold text-gray-900 mb-4">$0<span class="text-base font-normal text-gray-600">/mo</span></p>
                                <ul class="space-y-2 text-sm text-gray-700 mb-6">
                                    <li>✓ 1 monitor</li>
                                    <li>✓ 15-minute checks</li>
                                    <li>✓ Email alerts</li>
                                    <li>✓ 30-day history</li>
                                </ul>
                            </div>

                            <div class="border-2 border-emerald-500 rounded-lg p-6 relative">
                                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                    <span class="bg-emerald-600 text-white px-3 py-1 rounded-full text-xs font-semibold">Popular</span>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Pro</h3>
                                <p class="text-3xl font-bold text-gray-900 mb-4">$9<span class="text-base font-normal text-gray-600">/mo</span></p>
                                <ul class="space-y-2 text-sm text-gray-700 mb-6">
                                    <li>✓ 5 monitors</li>
                                    <li>✓ 10-minute checks</li>
                                    <li>✓ Email alerts</li>
                                    <li>✓ Unlimited history</li>
                                </ul>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-6">
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Team</h3>
                                <p class="text-3xl font-bold text-gray-900 mb-4">$29<span class="text-base font-normal text-gray-600">/mo</span></p>
                                <ul class="space-y-2 text-sm text-gray-700 mb-6">
                                    <li>✓ 20 monitors</li>
                                    <li>✓ 10-minute checks</li>
                                    <li>✓ 5 team members</li>
                                    <li>✓ Slack & webhooks</li>
                                    <li>✓ Priority support</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-8">
                            <a href="{{ route('billing.index') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors not-prose">
                                View Plans & Pricing
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($activeSection === 'faq')
                    <div class="prose prose-emerald max-w-none">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6">Frequently Asked Questions</h1>

                        <div class="space-y-6">
                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">How does uptime monitoring work?</h3>
                                <p class="text-gray-700">
                                    Monitly sends HTTP requests to your URLs at regular intervals (every 5-15 minutes depending on your plan). 
                                    If your site doesn't respond or returns an error, we immediately alert you via email, Slack, or webhooks.
                                </p>
                            </div>

                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">What happens when my monitor goes down?</h3>
                                <p class="text-gray-700">
                                    When a check fails, we wait for one more check to confirm the issue (to avoid false positives). 
                                    If the second check also fails, we create an incident and send notifications. We continue checking 
                                    and notify you when the site recovers.
                                </p>
                            </div>

                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I monitor APIs and not just websites?</h3>
                                <p class="text-gray-700">
                                    Yes! You can monitor any HTTP/HTTPS endpoint, including REST APIs, GraphQL endpoints, or any URL 
                                    that returns an HTTP response. We support all HTTP methods and can check specific status codes.
                                </p>
                            </div>

                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">How do I upgrade or downgrade my plan?</h3>
                                <p class="text-gray-700">
                                    Go to Billing in your dashboard. You can upgrade or downgrade at any time. 
                                    Upgrades take effect immediately, while downgrades take effect at the end of your billing period.
                                </p>
                            </div>

                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Do you offer a money-back guarantee?</h3>
                                <p class="text-gray-700">
                                    Yes! We offer a 30-day money-back guarantee on all paid plans. If you're not satisfied, 
                                    contact support for a full refund.
                                </p>
                            </div>

                            <div class="pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">How can I contact support?</h3>
                                <p class="text-gray-700 mb-4">
                                    We're here to help! You can reach us at:
                                </p>
                                <ul class="list-disc list-inside space-y-2 text-gray-700">
                                    <li>Email: <a href="mailto:support@monitly.app" class="text-emerald-600 hover:text-emerald-700">support@monitly.app</a></li>
                                    <li>Response time: Within 24 hours (priority for Team plans)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>

                <!-- Help Section -->
                <div class="mt-8 bg-gradient-to-r from-emerald-50 to-blue-50 border border-emerald-200 rounded-lg p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Still have questions?</h3>
                            <p class="text-sm text-gray-700 mb-4">
                                Our support team is here to help. Reach out anytime!
                            </p>
                            <a href="mailto:support@monitly.app" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-600 hover:text-emerald-700">
                                Contact Support
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>
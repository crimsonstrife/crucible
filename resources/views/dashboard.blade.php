<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Crucible Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Organizations --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                    <h3 class="text-lg font-semibold">
                        {{ __('Your organizations') }}
                    </h3>

                    @if ($ownedOrganizations->isEmpty() && $memberOrganizations->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('You are not part of any organizations yet.') }}
                        </p>
                    @else
                        @if ($ownedOrganizations->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-semibold mb-2">
                                    {{ __('Owned organizations') }}
                                </h4>
                                <ul class="space-y-1 text-sm">
                                    @foreach ($ownedOrganizations as $organization)
                                        <li class="flex items-center justify-between">
                                            <span class="font-medium">
                                                {{ $organization->name }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $organization->slug }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($memberOrganizations->isNotEmpty())
                            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-semibold mb-2">
                                    {{ __('Member organizations') }}
                                </h4>
                                <ul class="space-y-1 text-sm">
                                    @foreach ($memberOrganizations as $organization)
                                        <li class="flex items-center justify-between">
                                            <span class="font-medium">
                                                {{ $organization->name }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                @if ($organization->owner)
                                                    {{ __('Owner: :name', ['name' => $organization->owner->name]) }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Repositories --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                    <h3 class="text-lg font-semibold">
                        {{ __('Repositories you can access') }}
                    </h3>

                    @if ($repositories->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No repositories assigned to you yet.') }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach ($repositories as $repository)
                                <li class="py-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                    <div>
                                        <div class="font-medium">
                                            {{ $repository->name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if ($repository->organization)
                                                {{ $repository->organization->name }} /
                                            @endif
                                            {{ $repository->slug }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Visibility: :visibility', ['visibility' => $repository->visibility]) }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

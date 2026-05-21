<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('clients.index', [
            'clients' => Client::withCount('applications')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('clients.form', [
            'client' => new Client(['status' => 'active']),
            'action' => route('clients.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function show(Client $client): View
    {
        $client->load(['applications.servers', 'applications.urls.monitor.latestResult']);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        return view('clients.form', [
            'client' => $client,
            'action' => route('clients.update', $client),
            'method' => 'PATCH',
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('clients', 'code')->ignore($client?->id)],
            'environment' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'support_team' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

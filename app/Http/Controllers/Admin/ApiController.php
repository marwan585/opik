<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Api\KeyCreationService;
use Pterodactyl\Http\Requests\Admin\Api\StoreApplicationApiKeyRequest;
use Pterodactyl\Http\Middleware\AdminOwnerOnly;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ApiController extends Controller
{
    /**
     * ApiController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * Render view showing all of a user's application API keys.
     * Non-owner admins only see their OWN keys; the panel owner (ID 1) sees all.
     */
    public function index(Request $request): View
    {
        $query = ApiKey::query()->where('key_type', ApiKey::TYPE_APPLICATION);

        // Cloud Server protection: non-owner admins can only see their own keys.
        if (!AdminOwnerOnly::isOwner($request->user()?->id)) {
            $query->where('user_id', $request->user()->id);
        }

        return view('admin.api.index', [
            'keys' => $query->get(),
            'isOwner' => AdminOwnerOnly::isOwner($request->user()?->id),
        ]);
    }

    /**
     * Render view allowing an admin to create a new application API key.
     *
     * @throws \ReflectionException
     */
    public function create(): View
    {
        $resources = AdminAcl::getResourceList();
        sort($resources);

        return view('admin.api.new', [
            'resources' => $resources,
            'permissions' => [
                'r' => AdminAcl::READ,
                'rw' => AdminAcl::READ | AdminAcl::WRITE,
                'n' => AdminAcl::NONE,
            ],
        ]);
    }

    /**
     * Store the new key and redirect the user back to the application key listing.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(StoreApplicationApiKeyRequest $request): RedirectResponse
    {
        $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
            'memo' => $request->input('memo'),
            'user_id' => $request->user()->id,
        ], $request->getKeyPermissions());

        $this->alert->success('A new application API key has been generated for your account.')->flash();

        return redirect()->route('admin.api.index');
    }

    /**
     * Delete an application API key from the database.
     *
     * Cloud Server protection:
     *  - Panel owner (ID 1) can delete any application API key.
     *  - Non-owner admins can ONLY delete keys that belong to them.
     *  - Attempting to delete another admin's key throws 403.
     */
    public function delete(Request $request, string $identifier): Response
    {
        $key = ApiKey::query()
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('identifier', $identifier)
            ->first();

        if (!$key) {
            return response('', 204); // already gone — idempotent
        }

        $user = $request->user();
        $isOwner = AdminOwnerOnly::isOwner($user?->id);

        if (!$isOwner && (int) $key->user_id !== (int) $user->id) {
            throw new AccessDeniedHttpException(
                'Akses Ditolak. Anda hanya dapat menghapus API key milik Anda sendiri. ' .
                'Hanya Admin Utama (ID 1) yang dapat menghapus API key admin lainnya.'
            );
        }

        $key->delete();

        return response('', 204);
    }
}

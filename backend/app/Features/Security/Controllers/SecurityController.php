<?php

namespace App\Features\Security\Controllers;

use App\Features\Security\Actions\GetRiskProfile;
use App\Features\Security\Actions\GetSecurityDevices;
use App\Features\Security\Actions\GetSecurityHistory;
use App\Features\Security\Actions\GetSecurityProfile;
use App\Features\Security\Actions\GetSecurityRules;
use App\Features\Security\Actions\SubmitSecurityReport;
use App\Features\Security\Actions\VerifyDevice;
use App\Features\Security\Actions\VerifySession;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Requests\DeviceVerificationRequest;
use App\Features\Security\Requests\SecurityHistoryRequest;
use App\Features\Security\Requests\SecurityReportRequest;
use App\Features\Security\Requests\SessionVerificationRequest;
use App\Features\Security\Resources\SecurityPayloadResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Read-mostly API. Nothing here mutates another module's state; the write
 * endpoints only add security metadata and enqueue analysis.
 */
final class SecurityController extends Controller
{
    public function me(Request $request, GetSecurityProfile $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource($action->execute((string) $request->user()->id));
    }

    public function risk(Request $request, GetRiskProfile $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource($action->execute((string) $request->user()->id));
    }

    public function devices(Request $request, GetSecurityDevices $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource($action->execute((string) $request->user()->id));
    }

    public function history(SecurityHistoryRequest $request, GetSecurityHistory $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource(
            $action->execute((string) $request->user()->id, $request->limit()),
        );
    }

    public function rules(Request $request, GetSecurityRules $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource($action->execute());
    }

    public function report(SecurityReportRequest $request, SubmitSecurityReport $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource(
            $action->execute((string) $request->user()->id, $request->validated()),
        );
    }

    public function verifyDevice(DeviceVerificationRequest $request, VerifyDevice $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource(
            $action->execute((string) $request->user()->id, $request->validated(), $request->ip()),
        );
    }

    public function verifySession(SessionVerificationRequest $request, VerifySession $action): SecurityPayloadResource
    {
        $this->authorize('viewOwn', SecurityEvent::class);

        return new SecurityPayloadResource(
            $action->execute((string) $request->user()->id, $request->validated(), $request->ip()),
        );
    }
}

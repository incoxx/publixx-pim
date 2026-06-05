<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductReferenceProfile;
use App\Services\Conformance\ProfileConformanceChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Batch-Re-Check aller Produkte, die ein Profil referenzieren.
 *
 * Wird bei einer Regeländerung / Versionserhöhung eines Profils ausgelöst.
 * Da Profile vererben, betrifft eine Änderung an einem Eltern-Profil auch
 * Produkte der Kind-Profile → diese werden mit einbezogen.
 */
class RecheckProfileProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 2;

    private const CHUNK_SIZE = 500;

    public function __construct(public string $profileId)
    {
    }

    public function handle(ProfileConformanceChecker $checker): void
    {
        $profile = ProductReferenceProfile::find($this->profileId);
        if (!$profile) {
            return;
        }

        // Betroffene Profile = das Profil selbst + alle (transitiven) Kind-Profile
        $profileIds = $this->collectDescendantIds($profile);

        $count = 0;
        Product::whereIn('reference_profile_id', $profileIds)
            ->select('id', 'reference_profile_id')
            ->with('referenceProfile')
            ->chunkById(self::CHUNK_SIZE, function ($products) use ($checker, &$count) {
                foreach ($products as $product) {
                    try {
                        $checker->check($product, 'batch');
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning('RecheckProfileProducts: check failed', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('RecheckProfileProducts completed', [
            'profile_id' => $this->profileId,
            'rechecked' => $count,
        ]);
    }

    /**
     * Sammelt das Profil und alle abgeleiteten Profile (rekursiv).
     *
     * @return array<int, string>
     */
    private function collectDescendantIds(ProductReferenceProfile $profile): array
    {
        $ids = [$profile->id];
        $queue = [$profile->id];

        while (!empty($queue)) {
            $children = ProductReferenceProfile::whereIn('parent_profile_id', $queue)
                ->pluck('id')
                ->all();
            $new = array_diff($children, $ids);
            if (empty($new)) {
                break;
            }
            $ids = array_merge($ids, $new);
            $queue = $new;
        }

        return $ids;
    }
}

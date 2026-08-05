<?php

namespace Database\Seeders;

use App\DTOs\OfferAiInputSnapshot;
use App\DTOs\OfferContentGenerationSnapshot;
use App\Enums\AiProcessStatus;
use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Enums\OfferStatus;
use App\Enums\UserRole;
use App\Models\AiAnalysis;
use App\Models\AiGeneration;
use App\Models\Offer;
use App\Models\User;
use App\Services\GenerationInputHasher;
use App\Services\OfferInputHasher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public const DEMO_TRACKING_CODE = 'demo1234567890demo1234567890de';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Demo data cannot be seeded in production.'
            );
        }

        $this->seedAccounts();
        $this->seedOffers();
        $this->seedCampaigns();
        $this->seedTrackingLinkAndClicks();
        $this->seedConversions();
        $this->seedExpenses();
        $this->seedAiAnalysis();
        $this->seedAiGeneration();
    }

    private function seedAccounts(): void
    {
        $this->upsertUser('admin@example.test', 'Demo Admin', UserRole::Admin);
        $this->upsertUser('affiliate@example.test', 'Demo Affiliate', UserRole::Affiliate);
        $this->upsertUser('affiliate2@example.test', 'Demo Affiliate 2', UserRole::Affiliate);
    }

    private function upsertUser(string $email, string $name, UserRole $role): void
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['role' => $role->value]);

            return;
        }

        User::forceCreate([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => $role->value,
        ]);
    }

    private function seedOffers(): void
    {
        $affiliate = User::where('email', 'affiliate@example.test')->first();

        $this->upsertOffer(
            $affiliate->id,
            'DEMO — Fitness Offer',
            'https://demo.example.test/fitness-offer',
            '25.00',
            OfferStatus::Active,
            'Offre CPA démo pour la mise en forme physique.'
        );

        $this->upsertOffer(
            $affiliate->id,
            'DEMO — Draft Offer',
            'https://demo.example.test/draft-offer',
            '10.00',
            OfferStatus::Draft,
            'Offre brouillon pour démonstration.'
        );

        $this->upsertOffer(
            $affiliate->id,
            'DEMO — Archived Offer',
            'https://demo.example.test/archived-offer',
            '15.00',
            OfferStatus::Archived,
            'Offre archivée pour démonstration.'
        );
    }

    private function upsertOffer(
        int $userId,
        string $name,
        string $destinationUrl,
        string $payout,
        OfferStatus $status,
        string $description,
    ): Offer {
        $offer = Offer::where('user_id', $userId)->where('name', $name)->first();

        if ($offer) {
            $offer->update([
                'destination_url' => $destinationUrl,
                'payout' => $payout,
                'status' => $status,
                'description' => $description,
            ]);

            return $offer;
        }

        return Offer::forceCreate([
            'user_id' => $userId,
            'name' => $name,
            'destination_url' => $destinationUrl,
            'payout' => $payout,
            'status' => $status,
            'description' => $description,
        ]);
    }

    private function seedCampaigns(): void
    {
        $activeOffer = Offer::where('name', 'DEMO — Fitness Offer')->first();
        $draftOffer = Offer::where('name', 'DEMO — Draft Offer')->first();

        $now = now()->toDateTimeString();

        DB::table('campaigns')->updateOrInsert(
            ['offer_id' => $activeOffer->id, 'name' => 'DEMO — Active Campaign'],
            [
                'traffic_source' => 'Google Ads',
                'budget' => '500.00',
                'status' => CampaignStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('campaigns')->updateOrInsert(
            ['offer_id' => $draftOffer->id, 'name' => 'DEMO — Draft Campaign'],
            [
                'traffic_source' => 'Facebook',
                'budget' => '200.00',
                'status' => CampaignStatus::Draft->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function seedTrackingLinkAndClicks(): void
    {
        $activeCampaign = DB::table('campaigns')
            ->where('name', 'DEMO — Active Campaign')
            ->first();

        $now = now()->toDateTimeString();

        DB::table('tracking_links')->updateOrInsert(
            ['campaign_id' => $activeCampaign->id],
            [
                'code' => self::DEMO_TRACKING_CODE,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $trackingLink = DB::table('tracking_links')
            ->where('campaign_id', $activeCampaign->id)
            ->first();

        $today = CarbonImmutable::now();
        $lastMonth = $today->subDays(15);

        $clickData = [
            [
                'utm_content' => 'demo-click-1',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'demo-campaign',
                'created_at' => $today->toDateTimeString(),
                'updated_at' => $today->toDateTimeString(),
            ],
            [
                'utm_content' => 'demo-click-2',
                'utm_source' => 'facebook',
                'utm_medium' => 'social',
                'utm_campaign' => 'demo-campaign',
                'created_at' => $today->startOfDay()->toDateTimeString(),
                'updated_at' => $today->startOfDay()->toDateTimeString(),
            ],
            [
                'utm_content' => 'demo-click-3',
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => 'demo-campaign',
                'created_at' => $lastMonth->toDateTimeString(),
                'updated_at' => $lastMonth->toDateTimeString(),
            ],
        ];

        foreach ($clickData as $click) {
            DB::table('tracking_clicks')->updateOrInsert(
                [
                    'tracking_link_id' => $trackingLink->id,
                    'utm_content' => $click['utm_content'],
                ],
                [
                    'ip_hash' => null,
                    'user_agent' => 'Mozilla/5.0 (Demo)',
                    'referer' => 'https://demo.example.test',
                    'utm_source' => $click['utm_source'],
                    'utm_medium' => $click['utm_medium'],
                    'utm_campaign' => $click['utm_campaign'],
                    'utm_term' => null,
                    'created_at' => $click['created_at'],
                    'updated_at' => $click['updated_at'],
                ]
            );
        }
    }

    private function seedConversions(): void
    {
        $activeCampaign = DB::table('campaigns')
            ->where('name', 'DEMO — Active Campaign')
            ->first();

        $today = CarbonImmutable::now();
        $lastMonth = $today->subDays(15);

        $now = now()->toDateTimeString();

        DB::table('conversions')->updateOrInsert(
            ['external_id' => 'demo-conversion-approved-1'],
            [
                'campaign_id' => $activeCampaign->id,
                'source' => 'google',
                'revenue' => '25.00',
                'status' => ConversionStatus::Approved->value,
                'converted_at' => $today->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('conversions')->updateOrInsert(
            ['external_id' => 'demo-conversion-approved-2'],
            [
                'campaign_id' => $activeCampaign->id,
                'source' => 'facebook',
                'revenue' => '25.00',
                'status' => ConversionStatus::Approved->value,
                'converted_at' => $lastMonth->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('conversions')->updateOrInsert(
            ['external_id' => 'demo-conversion-pending-1'],
            [
                'campaign_id' => $activeCampaign->id,
                'source' => 'newsletter',
                'revenue' => '25.00',
                'status' => ConversionStatus::Pending->value,
                'converted_at' => $lastMonth->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function seedExpenses(): void
    {
        $activeCampaign = DB::table('campaigns')
            ->where('name', 'DEMO — Active Campaign')
            ->first();

        $today = CarbonImmutable::now();
        $lastMonth = $today->subDays(15);
        $now = now()->toDateTimeString();

        DB::table('campaign_expenses')->updateOrInsert(
            ['campaign_id' => $activeCampaign->id, 'description' => 'DEMO — Expense 1'],
            [
                'amount' => '40.00',
                'spent_at' => $lastMonth->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('campaign_expenses')->updateOrInsert(
            ['campaign_id' => $activeCampaign->id, 'description' => 'DEMO — Expense 2'],
            [
                'amount' => '30.00',
                'spent_at' => $today->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function seedAiAnalysis(): void
    {
        $activeOffer = Offer::where('name', 'DEMO — Fitness Offer')->first();

        $snapshot = OfferAiInputSnapshot::fromOffer($activeOffer);
        $hasher = new OfferInputHasher;
        $inputHash = $hasher->compute($snapshot);

        AiAnalysis::updateOrCreate(
            ['offer_id' => $activeOffer->id],
            [
                'status' => AiProcessStatus::Completed,
                'score' => 85,
                'summary' => "L'offre CPA pour la mise en forme physique présente un excellent potentiel de conversion avec un panier moyen attractif.",
                'strengths' => ['Payout compétitif', 'Cible large', 'Taux de conversion élevé'],
                'weaknesses' => ['Marché concurrentiel', 'Saisonnalité possible'],
                'recommendations' => ['Optimiser les landing pages', 'Cibler les audiences fitness', 'Tester plusieurs créatives'],
                'input_hash' => $inputHash,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'completed_at' => CarbonImmutable::now()->subHour()->toDateTimeString(),
            ]
        );
    }

    private function seedAiGeneration(): void
    {
        $activeOffer = Offer::where('name', 'DEMO — Fitness Offer')->first();
        $analysis = AiAnalysis::where('offer_id', $activeOffer->id)->first();

        $snapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($activeOffer, $analysis);
        $hasher = new GenerationInputHasher;
        $inputHash = $hasher->compute($snapshot);

        AiGeneration::updateOrCreate(
            ['offer_id' => $activeOffer->id],
            [
                'status' => AiProcessStatus::Completed,
                'hooks' => [
                    'Transforme ton corps en 30 jours avec notre programme certifié',
                    'Rejoins des milliers de membres satisfaits dès aujourd\'hui',
                    'Offre exclusive : commence ton parcours fitness maintenant',
                ],
                'captions' => [
                    'Prêt à changer ta vie ? Notre programme CPA te guide vers la forme idéale avec des résultats prouvés et un suivi personnalisé.',
                    'Ne laisse plus rien te retenir. Découvre une approche unique du fitness qui combine nutrition, entraînement et motivation.',
                    'Chaque journey commence par un premier pas. Fais le tien avec notre offre spéciale réservée aux ambitieux.',
                ],
                'input_hash' => $inputHash,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'completed_at' => CarbonImmutable::now()->subMinutes(30)->toDateTimeString(),
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\Winner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WinnerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding winners...');

        $winners = [
            [
                'auction_id'       => 'AUC-2024-001',
                'item_id'          => 'ITM-001',
                'bidder_id'        => 'USR-001',
                'bidder_name'      => 'Budi Santoso',
                'bidder_email'     => 'budi.santoso@email.com',
                'item_name'        => 'Laptop Asus ROG G16 RTX 4070',
                'winning_bid'      => 18500000.00,
                'starting_price'   => 12000000.00,
                'bid_id'           => 'BID-2024-0101',
                'status'           => 'invoiced',
                'auction_ended_at' => Carbon::now()->subDays(2),
            ],
            [
                'auction_id'       => 'AUC-2024-002',
                'item_id'          => 'ITM-002',
                'bidder_id'        => 'USR-002',
                'bidder_name'      => 'Siti Rahma',
                'bidder_email'     => 'siti.rahma@email.com',
                'item_name'        => 'iPhone 15 Pro Max 256GB',
                'winning_bid'      => 21000000.00,
                'starting_price'   => 18000000.00,
                'bid_id'           => 'BID-2024-0202',
                'status'           => 'invoiced',
                'auction_ended_at' => Carbon::now()->subDays(5),
            ],
            [
                'auction_id'       => 'AUC-2024-003',
                'item_id'          => 'ITM-003',
                'bidder_id'        => 'USR-003',
                'bidder_name'      => 'Ahmad Fauzi',
                'bidder_email'     => 'ahmad.fauzi@email.com',
                'item_name'        => 'Kamera Sony A7 IV Full Frame',
                'winning_bid'      => 35000000.00,
                'starting_price'   => 28000000.00,
                'bid_id'           => 'BID-2024-0305',
                'status'           => 'invoiced',
                'auction_ended_at' => Carbon::now()->subWeek(),
            ],
            [
                'auction_id'       => 'AUC-2024-004',
                'item_id'          => 'ITM-004',
                'bidder_id'        => 'USR-004',
                'bidder_name'      => 'Dewi Kusuma',
                'bidder_email'     => 'dewi.kusuma@email.com',
                'item_name'        => 'Motor Vespa Primavera 150',
                'winning_bid'      => 48000000.00,
                'starting_price'   => 40000000.00,
                'bid_id'           => 'BID-2024-0401',
                'status'           => 'paid',
                'auction_ended_at' => Carbon::now()->subMonth(),
            ],
            [
                'auction_id'       => 'AUC-2024-005',
                'item_id'          => 'ITM-005',
                'bidder_id'        => 'USR-005',
                'bidder_name'      => 'Rizky Pratama',
                'bidder_email'     => 'rizky.pratama@email.com',
                'item_name'        => 'Drone DJI Mini 4 Pro',
                'winning_bid'      => 9500000.00,
                'starting_price'   => 7000000.00,
                'bid_id'           => 'BID-2024-0503',
                'status'           => 'pending',
                'auction_ended_at' => Carbon::now()->subHours(3),
            ],
        ];

        foreach ($winners as $data) {
            Winner::updateOrCreate(
                ['auction_id' => $data['auction_id']],
                $data
            );
        }

        $this->command->info('✅ Winners seeded: ' . count($winners) . ' records.');
    }
}

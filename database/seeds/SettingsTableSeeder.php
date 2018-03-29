<?php

use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('settings')->insert([
            'origin_id' => '6',
            'label' => 'next',
            'value' => 0,
            'description' => 'Next page of results for the octoparts api.',
        ]);
    }
}

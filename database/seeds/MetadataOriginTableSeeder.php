<?php

use Illuminate\Database\Seeder;

class MetadataOriginTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('metadata_origin')->insert(['label' => 'oemstrade.com']);
        DB::table('metadata_origin')->insert(['label' => 'verical.com']);
        DB::table('metadata_origin')->insert(['label' => 'vyrian.com']);
        DB::table('metadata_origin')->insert(['label' => '4starelectronics.com']);
        DB::table('metadata_origin')->insert(['label' => '1sourcecomponents.com']);
        DB::table('metadata_origin')->insert(['label' => 'octoparts.com']);
    }
}

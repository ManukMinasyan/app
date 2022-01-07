<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use PragmaRX\Countries\Package\Countries;

class CustomersSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $countries = new Countries();
        $filePath = database_path('seeders/random.csv');
        $errorsFilePath = database_path('seeders/errors.csv');

        File::delete($errorsFilePath);

        $customersCSV = fopen($filePath, 'r');
        $errorsCSV = fopen($errorsFilePath, 'w');

        $this->info('Customers sync started!');

        LazyCollection::make(function () use ($customersCSV) {
            while ($line = fgetcsv($customersCSV)) {
                yield $line;
            }
        })
            ->skip(1)
            ->map(function (array $row) use ($countries) {
                $name = explode(' ', $row[1]);
                $country = $countries->where('name.common', trim($row[4]))->first();
                return [
                    'name' => $name[0],
                    'surname' => $name[1],
                    'email' => $row[2],
                    'age_number' => (int)$row[3],
                    'age' => Carbon::now()->subMonths((int)$row[3])->toDateTimeString(),
                    'location' => $country['adm0_a3_is'] ? $row[4] : 'Unknown',
                    'country_code' => $country['adm0_a3_is'] ?? null
                ];
            })
            ->reject(function ($customer) use ($errorsFilePath, $errorsCSV) {
                $validation = validator($customer, [
                    'email' => 'email:rfc,dns',
                    'age_number' => 'numeric|min:18|max:99'
                ]);

                if ($validation->fails()) {
                    $errors = '';

                    foreach ($validation->errors()->toArray() as $key => $row) {
                        $errors .= $key . ': ' . implode(',', $row);
                    }

                    fputcsv($errorsCSV, [$customer['email'], $errors]);
                }

                return $validation->fails();
            })
            ->chunk(10)
            ->each(function ($customers) {
                Customer::upsert($customers->map(fn($customer) => Arr::only($customer, (new Customer())->getFillable()))->toArray(), 'email');
            });

        fclose($customersCSV);
        fclose($errorsCSV);

        $this->info('Customers sync successfully finished.');

        return 0;
    }
}

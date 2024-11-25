<?php 
namespace App\Imports;
use App\Models\TrafficChart;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class TrafficChartImport implements ToModel, WithHeadingRow, WithValidation
{
    private $rowCount = 0;
    private $matchad_Type = ['text', 'banner', 'social', 'native', 'popunder'];
    private $matched_Device = ['desktop', 'tablet', 'mobile'];
    private $matched_Os = ['android', 'apple', 'windows', 'linux'];
    public function model(array $row)
    {
        $this->rowCount++;
        $uni_traffic_id = md5(strtolower($row['traffic_type'])  . "-" . strtolower($row['ad_type'])  . "-" . strtolower($row['country'])  . "-" . strtolower($row['device_type'])  . "-" . strtolower($row['device_os']));
        $traffic = TrafficChart::where('uni_traffic_id', $uni_traffic_id)->first();
        if (!$traffic) {
            $traffic = new TrafficChart();
            $traffic->uni_traffic_id = $uni_traffic_id;
        }
        $traffic->traffic_type = strtolower($row['traffic_type']);
        $traffic->ad_type = strtolower($row['ad_type']);
        $traffic->country = strtolower($row['country']);
        $traffic->device_type = strtolower($row['device_type']);
        $traffic->device_os = strtolower($row['device_os']);
        $traffic->traffic = $row['traffic'];
        $traffic->avg_bid = $row['avg_bid'];
        $traffic->high_bid = $row['high_bid'];
        $traffic->save();

        return $traffic;
    }

    public function getRowCount()
    {
        return $this->rowCount;
    }

    public function rules(): array
    {
        $country_data = DB::table('countries')
            ->where('status', 1)
            ->where('trash', 1)
            ->select(DB::raw('LOWER(name) as name'))
            ->pluck('name')
            ->toArray();
        return [
            '*.traffic_type' => ['required', Rule::in(['cpm', 'cpc'])],
            '*.ad_type' => ['required', 'string', Rule::in($this->matchad_Type)],
            '*.country' => ['required', 'string', function ($attribute, $value, $fail) use ($country_data) {
                if (!in_array(strtolower($value), $country_data)) {
                    $fail($value . ' ' . $attribute . ' name is not exists!');
                }
            }],
            '*.device_type' => ['required', 'string', Rule::in($this->matched_Device)],
            '*.device_os' => ['required', 'string', Rule::in($this->matched_Os)],
            '*.traffic' => ['required', 'numeric'],
            '*.avg_bid' => ['required', 'numeric'],
            '*.high_bid' => ['required', 'numeric'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.traffic_type.in' => 'Invalid traffic type, allowed only: cpm or cpc!',
            '*.ad_type.in' => 'Invalid ad type, allowed only: ' . implode(',', $this->matchad_Type),
            '*.device_type.in' => 'Invalid device type, allowed only: ' . implode(',', $this->matched_Device),
            '*.device_os.in' => 'Invalid device os, allowed only: ' . implode(',', $this->matched_Os),
        ];
    }
}


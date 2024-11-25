<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransactionLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentAdminGraphController extends Controller
{
     public function monthlyReport(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $validator = Validator::make($request->all(), [
            "payment_year" => ["required","integer","min:1900","max:$currentYear"],
         ]);
        if ($validator->fails()) {
            return response()->json(
                [
                    "code" => 100,
                    "error" => $validator->errors(),
                    "message" => "Validation error!",
                ],
                422
            );
        }
        $paymentYear = $request->payment_year;
        $startMonth = "{$paymentYear}-01-01 00:00:00";
        $endMonth = $paymentYear == date("Y")? date("Y-m-d H:i:s"): "{$paymentYear}-12-31 23:59:59";
        $carbonDate = Carbon::createFromFormat("Y-m-d H:i:s", $endMonth);
        $monthNumber = $carbonDate->format("m");
        $res = self::monthlyReportNewPayment($paymentYear,$startMonth,$endMonth,$monthNumber);
        $allPaymentsQuery = Transaction::select("transactions.advertiser_code","transactions.payble_amt",
            DB::raw('DATE_FORMAT(ss_transactions.created_at, "%Y-%m") as month')
        )
            ->whereBetween("transactions.created_at", [$startMonth, $endMonth])
            ->join("users", "users.uid", "=", "transactions.advertiser_code")
            ->join("transaction_logs","transaction_logs.transaction_id","=",
                "transactions.transaction_id"
            )
            ->where("users.account_type", 0)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->where("transaction_logs.cpn_typ", 0);
        $payments = $allPaymentsQuery->get();
        $monthlyPayments = $payments
            ->groupBy("month")
            ->map(function ($monthPayments) {
                return $monthPayments->sum("payble_amt");
            });
        for ($i = 1; $i <= (int) $monthNumber; $i++) {
            $monthKey = $paymentYear . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);
            if (!isset($monthlyPayments[$monthKey])) {
                $monthlyPayments[$monthKey] = 0;
            }
        }
        $sortedMonthlyPayments = collect($monthlyPayments)->sortKeys();
        $paymentData = $sortedMonthlyPayments
            ->map(function ($amount, $month) {
                $carbonDate = Carbon::createFromFormat("Y-m-d", $month.'-01');
                return [
                    "month" =>$carbonDate->format("M") ." " .'$' .number_format($amount, 2),
                    "totalPayment" => $amount,
                ];
            })
            ->values();
        $response = [
            "monthPayment" => $paymentData->pluck("month"),
            "totalPayment" => $paymentData->pluck("totalPayment"),
        ];
        for ($i = 0; $i < (int) $monthNumber; $i++) {
            $results["monthPayment"][] = $response["monthPayment"][$i];
            $results["newPayment"][] = $res[$i];
            $results["repeatPayment"][] =
            $response["totalPayment"][$i] - $res[$i];
            $results["totalPayment"][] = $response["totalPayment"][$i];
        }
        $result = $results;
        if ($result) {
            return response()->json([
                "code" => 200,
                "data" => $result,
                "message" => "Data Found Successfully!",
            ]);
        } else {
            return response()->json([
                "code" => 101,
                "data" => [],
                "message" => "Data Not Found!",
            ]);
        }
    }
    static function monthlyReportNewPayment($paymentYear,$startMonth,$endMonth, $monthNumber) {
        $firstTimeTransactionsQuery = Transaction::select("transactions.advertiser_code",
            DB::raw("MIN(ss_transactions.created_at) as first_transaction_date")
        )
            ->whereBetween("transactions.created_at", [$startMonth, $endMonth])
            ->join("users", "users.uid", "=", "transactions.advertiser_code")
            ->join("transaction_logs","transaction_logs.transaction_id","=","transactions.transaction_id")
            ->where("users.account_type", 0)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->where("transaction_logs.cpn_typ", 0)
            ->groupBy("transactions.advertiser_code");
        $payments = Transaction::select("transactions.advertiser_code", "transactions.payble_amt",DB::raw('DATE_FORMAT(ss_transactions.created_at, "%Y-%m") as month'))
            ->joinSub($firstTimeTransactionsQuery->toBase(),"first_time_transactions",
                function ($join) {$join
                     ->on("transactions.advertiser_code","=","first_time_transactions.advertiser_code")
                     ->on("transactions.created_at","=","first_time_transactions.first_transaction_date");
                }
            )
            ->whereBetween("transactions.created_at", [$startMonth, $endMonth])
            ->get();
        $monthlyPayments = $payments
            ->groupBy("month")
            ->map(function ($monthPayments) {
                return $monthPayments->sum("payble_amt");
            });
        for ($i = 1; $i <= (int) $monthNumber; $i++) {
            $monthKey = $paymentYear . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);
            if (!isset($monthlyPayments[$monthKey])) {
                $monthlyPayments[$monthKey] = 0;
            }
        }
        $sortedMonthlyPayments = collect($monthlyPayments)->sortKeys();
        $paymentData = $sortedMonthlyPayments
            ->map(function ($amount, $month) {
                $carbonDate = Carbon::createFromFormat("Y-m", $month);
                return [
                    "month" => $carbonDate->format("M") ." " .'$' .number_format($amount, 2),
                    "totalPayment" => $amount,
                ];
            })
            ->values();
        return $paymentData->pluck("totalPayment");
    }
    public function dailyTarget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "payment_date" => "required",
        ]);
        if ($validator->fails()) {
            $return["code"] = 100;
            $return["data"] =[];
            $return["error"] = $validator->errors();
            $return["message"] = "Valitation error!";
            return json_encode($return);
        }
        if ($request->payment_date == date("Y-m-d")) {
            $date = $request->payment_date;
        } else {
            $date = $request->payment_date;
            $date =$request->payment_date === date("Y-m-d") ? date("Y-m-d") : Carbon::parse($date)->endOfMonth()->toDateString();
        }
        $dateInstance = Carbon::parse($date);
        $month = $dateInstance->month;
        $year = $dateInstance->year;
        $totaDay = $dateInstance->day;
        $endOfMonth = Carbon::parse($date)->endOfMonth()->toDateString();
        $payments = DB::table("transactions")
            ->selectRaw(
                "SUM(CASE WHEN DATE(ss_transactions.created_at) = ? THEN ss_transactions.payble_amt ELSE 0 END) as todayAmount",[$date])
            ->join("users","users.uid","=","transactions.advertiser_code")
            ->join("transaction_logs","transaction_logs.transaction_id","=","transactions.transaction_id")
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->where("transaction_logs.cpn_typ", 0)
            ->where("users.account_type", 0)
            ->whereBetween(DB::raw("DATE(ss_transaction_logs.created_at)"), [$date,$endOfMonth,])->first();
        $averageMonthlyAmount = Transaction::join("transaction_logs","transaction_logs.transaction_id","=","transactions.transaction_id")
            ->join("users", "users.uid", "=", "transactions.advertiser_code")
            ->whereMonth("transaction_logs.created_at", $month)
            ->whereYear("transaction_logs.created_at", $year)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->where("transaction_logs.cpn_typ", 0)
            ->where("users.account_type", 0)
            ->sum("transactions.payble_amt");
        $response = [
            "todayAmount" => $request->payment_date === date("Y-m-d") ? $payments->todayAmount : 0 ,
            "averageMonthlyAmount" => ($averageMonthlyAmount / $totaDay),
        ];
        return response()->json([
            "code" => 200,
            "data" => $response,
            "message" => "Data Found Successfully!",
        ]);
    }
    public function dailyProgress(Request $request)
    {
        $validator = Validator::make(
          $request->all(),
          [
            'year_month' => 'required|date|before_or_equal:today'
          ],[
            'year_month.before_or_equal' => "The :attribute must be a before or current month."
          ]
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    "code" => 100,
                    "data" => [],
                    "error" => $validator->errors(),
                    "message" => "Validation error!",
                ],
                422
            );
        }

        $yearMonth = $request->year_month;
        $sdate = "{$yearMonth}-01";
        $edate = $yearMonth === date("Y-m") ? date("Y-m-d") : Carbon::parse($sdate)->endOfMonth()->format("Y-m-d");
        $payments = DB::table("transactions")
            ->selectRaw("DATE(ss_transaction_logs.created_at) as date, SUM(ss_transactions.payble_amt) as dailyAmount")
            ->join("users", "users.uid", "=", "transactions.advertiser_code")
            ->join("transaction_logs", "transaction_logs.transaction_id", "=", "transactions.transaction_id")
            ->where("users.account_type", 0)
            ->where("transaction_logs.cpn_typ", 0)
            ->where("users.user_type", "!=", 2)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->groupBy(DB::raw("DATE(ss_transaction_logs.created_at)"))
            ->whereBetween(DB::raw("DATE(ss_transaction_logs.created_at)"), [$sdate, $edate])
            ->get();
        $calendarData = $payments->pluck("dailyAmount", "date")->toArray();
        $period = Carbon::parse($sdate)->daysUntil(Carbon::parse($edate));
        foreach ($period as $date) {
            $formattedDate = $date->format("Y-m-d");
            if (!array_key_exists($formattedDate, $calendarData)) {
                $calendarData[$formattedDate] = 0;
            }
        }
        ksort($calendarData);
        $response = [
            "date" => array_map(function ($date) {
                return Carbon::parse($date)->format("d F Y");
            }, array_keys($calendarData)),
            "dailyAmount" => array_values($calendarData),
        ];

        return response()->json([
            "code" => 200,
            "data" => $response,
            "message" => "Data Retrieved Successfully!",
        ]);
    }
    public function sourcesPayment(Request $request)
    {
         $validator = Validator::make(
          $request->all(),
          [
            'source_month' => 'required|date|before_or_equal:today'
          ],[
            'source_month.before_or_equal' => "The :attribute must be a before or current month."
          ]
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    "code" => 100,
                    "data" => [],
                    "error" => $validator->errors(),
                    "message" => "Validation error!",
                ],
                422
            );
        }

        $sourceMonth = $request->source_month;
        $sdate = "{$sourceMonth}-01";
        $edate = $sourceMonth === date("Y-m") ? date("Y-m-d") : Carbon::parse($sdate)->endOfMonth()->toDateString();
        $payments = DB::table("transactions")
            ->selectRaw("SUM(ss_transactions.payble_amt) as totalAmount, ss_users.auth_provider as source,ss_sources.title")
            ->join("transaction_logs","transaction_logs.transaction_id","=","transactions.transaction_id")
            ->join("users","users.uid","=","transaction_logs.advertiser_code")
            ->join("sources","sources.source_type","=","users.auth_provider")
            ->where("users.account_type", 0)
            ->where("transaction_logs.cpn_typ", 0)
            ->where("users.user_type", "!=", 2)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->where("transactions.payment_mode", "!=", "coupon")
            ->whereBetween(DB::raw("DATE(ss_transactions.created_at)"), [$sdate,$edate,])
            ->groupBy("sources.source_type")
            ->get();
        $overallTotalAmount = $payments->sum("totalAmount");
        $payments = $payments->map(function ($item) use ($overallTotalAmount) {
        $item->percentage = $overallTotalAmount > 0 ? number_format(($item->totalAmount / $overallTotalAmount) * 100,2): 0;
            return $item;
        });
        $response = [];
        foreach ($payments as $payment) {
            $response[] = [
                "dailyAmounts" => $payment->totalAmount,
                "sources" => $payment->title,
                "percentages" => $payment->percentage,
                "additionalInfos" => $payment->source,
            ];
        }

        if ($payments->isNotEmpty()) {
            return response()->json([
                "code" => 200,
                "data" => $response,
                "message" => "Data Found Successfully!",
            ]);
        } else {
            return response()->json([
                "code" => 101,
                "data" => [],
                "message" => "Data Not Found!",
            ]);
        }
    }

    public function paymentGateways(Request $request)
    {
         $validator = Validator::make(
          $request->all(),
          [
            'source_month' => 'required|date|before_or_equal:today'
          ],[
            'source_month.before_or_equal' => "The :attribute must be a before or current month."
          ]
        );
        if ($validator->fails()) {
            return response()->json(
                [
                    "code" => 100,
                    "data" => [],
                    "error" => $validator->errors(),
                    "message" => "Validation error!",
                ],
                422
            );
        }

        $sourceMonth = $request->source_month;
        $sdate = "{$sourceMonth}-01";
        $edate = $sourceMonth === date("Y-m") ? date("Y-m-d") : Carbon::parse($sdate)->endOfMonth()->toDateString();
        $payments = DB::table("transactions")
        ->selectRaw("SUM(ss_transactions.payble_amt) as totalAmount, ss_transactions.payment_mode as gateway, ss_payment_gateways.title")
        ->join("transaction_logs","transaction_logs.transaction_id","=","transactions.transaction_id")
        ->join("users","users.uid","=","transaction_logs.advertiser_code")
        ->join("payment_gateways","payment_gateways.gateway_value","=","transactions.payment_mode")
        ->where("users.account_type", 0)
        ->where("transaction_logs.cpn_typ", 0)
        ->where("users.user_type", "!=", 2)
        ->where("transactions.status", 1)
        ->where("transactions.payment_mode", "!=", "bonus")
        ->where("transactions.payment_mode", "!=", "coupon")
        ->whereBetween(DB::raw("DATE(ss_transaction_logs.created_at)"), [$sdate,$edate,])
        ->groupBy("payment_gateways.gateway_value")
        ->get();
        $overallTotalAmount = $payments->sum("totalAmount");
        $payments = $payments->map(function ($item) use ($overallTotalAmount) {
            $item->percentage = $overallTotalAmount > 0 ? number_format(($item->totalAmount / $overallTotalAmount) * 100,2) : 0;
            return $item;
        });
        $response = [];
        foreach ($payments as $payment) {
            $response[] = [
                "totalAmount" => $payment->totalAmount,
                "gateway" => $payment->title,
                "percentage" => $payment->percentage,
                "additionalInfo" => $payment->gateway,
            ];
        }

        if ($payments->isNotEmpty()) {
            return response()->json([
                "code" => 200,
                "data" => $response,
                "message" => "Data Found Successfully!",
            ]);
        } else {
            return response()->json([
                "code" => 101,
                "data" => [],
                "message" => "Data Not Found!",
            ]);
        }
    }
    public function countryWisePayment(Request $request)
    {
         $validator = Validator::make(
          $request->all(),
          [
            'country_month' => 'required|date|before_or_equal:today'
          ],[
            'country_month.before_or_equal' => "The :attribute must be a before or current month."
          ]
        );
        if ($validator->fails()) {
            return response()->json(
                [
                    "code" => 100,
                    "data" => [],
                    "error" => $validator->errors(),
                    "message" => "Validation error!",
                ],
                422
            );
        }

        $countryMonth = $request->country_month;
        $sdate = "{$countryMonth}-01";
        $edate = $countryMonth === date("Y-m") ? date("Y-m-d") : Carbon::parse($sdate)->endOfMonth()->toDateString();
        $payments = DB::table("transactions")
            ->selectRaw(
                "SUM(ss_transactions.payble_amt) as totalAmount, ss_users.country as country"
            )
            ->join("users", "transactions.advertiser_code", "=", "users.uid")
            ->join("transaction_logs","transaction_logs.transaction_id", "=","transactions.transaction_id")
            ->whereBetween(DB::raw("DATE(ss_transaction_logs.created_at)"), [$sdate,$edate,])
            ->where("users.account_type", 0)
            ->where("transaction_logs.cpn_typ", 0)
            ->where("users.user_type", "!=", 2)
            ->where("transactions.status", 1)
            ->where("transactions.payment_mode", "!=", "bonus")
            ->groupBy("users.country")
            ->get();
        $overallTotalAmount = $payments->sum("totalAmount");
        $payments = $payments->map(function ($item) use ($overallTotalAmount) {
            $item->percentage =
                $overallTotalAmount > 0
                    ? number_format(
                        ($item->totalAmount / $overallTotalAmount) * 100,
                        2
                    )
                    : 0;
            return $item;
        });
        $response = [];
        foreach ($payments as $payment) {
            $response[] = [
                "country" => $payment->country,
                "totalAmount" => $payment->totalAmount,
                "percentage" => $payment->percentage,
            ];
        }

        if ($payments->isNotEmpty()) {
            return response()->json([
                "code" => 200,
                "data" => $response,
                "message" => "Data Found Successfully!",
            ]);
        } else {
            return response()->json([
                "code" => 101,
                "data" => [],
                "message" => "Data Not Found!",
            ]);
        }
    }
}

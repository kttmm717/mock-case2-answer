<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $date = $this->faker->dateTimeBetween('2024-01-01', '2025-03-31')->format('Y-m-d');

        $clock_in = Carbon::parse($date)->setTimeFromTimeString($this->faker->time('H:i:s'));
        $clock_out = $this->faker->dateTimeBetween($clock_in, Carbon::parse($date)->endOfDay());

        $break_time_limit = 2*60*60;
        $break_in = Carbon::parse($this->faker->dateTimeBetween($clock_in, $clock_out));
        $break_out = Carbon::parse($this->faker->dateTimeBetween($break_in, $clock_out));
        $break2_in = Carbon::parse($this->faker->dateTimeBetween($break_out, $clock_out));
        $break2_out = Carbon::parse($this->faker->dateTimeBetween($break2_in, $clock_out));

        $first_break_duration = $break_in->diffInSeconds($break_out);
        $second_break_duration = $break2_in->diffInSeconds($break2_out);

        //休憩時間の秒
        $total_break_time = min($first_break_duration + $second_break_duration, $break_time_limit);
        //休憩差し引いた勤務時間の秒
        $total_time = $clock_in->diffInSeconds($clock_out) - $total_break_time;

        return [
            'user_id' => $this->faker->randomElement([1,2,3]),
            'date' => $date,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out,
            'break_in' => $break_in,
            'break_out' => $break_out,
            'break2_in' => $break2_in,
            'break2_out' => $break2_out,
            'total_break_time' => gmdate('H:i', $total_break_time),
            'total_time' => gmdate('H:i', $total_time),
        ];
    }
}

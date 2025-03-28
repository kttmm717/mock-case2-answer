<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;
use App\Models\User;
use Carbon\CarbonInterval;
use App\Models\Application;
use App\Http\Requests\CorrectionRequest;

class UserController extends Controller
{
    public function index() {
        $user = User::find(Auth::id());

        if($user->attendance_status === '退勤済') {
            $attendance = AttendanceRecord::where('user_id', $user->id)
                                        ->whereDate('date', now()->format('Y-m-d'))
                                        ->first();
            if(!$attendance) {
                $user->update([
                    'attendance_status' => '勤務外'
                ]);
            }
        }
        $now = Carbon::now();
        $week = ['日', '月', '火', '水', '木', '金', '土'];
        $weekdayIndex = $now->dayOfWeek; //曜日番号を取得
        $weekday = $week[$weekdayIndex];
        $formattedDate = $now->format('Y年m月d日('. $weekday . ')');
        $formattedTime = $now->format('H:i');

        return view('user/attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }
    public function attendance(Request $request) {
        $user = User::find(Auth::id());
        $action = $request->input('action');

        if($user->attendance_status !== '勤務外') {
            $attendance = AttendanceRecord::where('user_id', $user->id)
                                        ->whereDate('date', now()->format('Y-m-d'))
                                        ->first();
        }
        if($user->attendance_status !== '退勤済') {
            $attendance = AttendanceRecord::where('user_id', $user->id)
                                        ->whereDate('date', now()->format('Y-m-d'))
                                        ->first();
            if(!$attendance) {
                $user->update([
                    'attendance_status' => '勤務外'
                ]);
            }
        }

        if($action === 'clock_in' && $user->attendance_status === '勤務外') {
            $attendance = AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => now(),
                'clock_in' => now()->format('H:i'),
            ]);
            $user->update([
                'attendance_status' => '出勤中'
            ]);
        }elseif($action === 'clock_out' && $user->attendance_status === '出勤中') {
            $clock_out = Carbon::now()->format('H:i');
            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = Carbon::parse($attendance->clock_out);

            $totalBreakTime = 0;
            if($attendance->break_in && $attendance->break_out) {
                $breakIn = Carbon::parse($attendance->break_in);
                $breakOut = Carbon::parse($attendance->break_out);
                $totalBreakTime += $breakIn->diffInMinutes($breakOut);
            }
            if($attendance->break2_in && $attendance->break2_out) {
                $break2In = Carbon::parse($attendance->break2_in);
                $break2Out = Carbon::parse($attendance->break2_out);
                $totalBreakTime += $break2In->diffInMinutes($break2Out);
            }
            $total_break_time = CarbonInterval::minutes($totalBreakTime)->cascade()->format('%H:%I');

            $totalWorkedMinuted = $clockIn->diffInMinutes($clockOut) - $totalBreakTime;
            $total_time = CarbonInterval::minutes($totalWorkedMinuted)->cascade()->format('%H:%I');

            $attendance->update([
                'clock_out' => $clock_out,
                'total_time' => $total_time,
                'total_break_time' => $total_break_time,
            ]);
            $user->update([
                'attendance_status' => '退勤済'
            ]);
        }elseif($action === 'break_in' && $user->attendance_status === '出勤中') {
            if(!$attendance->break_in) {
                $attendance->break_in = Carbon::now()->format('H:i');
            }elseif(!$attendance->break2_in) {
                $attendance->break2_in = Carbon::now()->format('H:i');
            }
            $attendance->save();
            $user->update([
                'attendance_status' => '休憩中',
            ]);
        }elseif($action === 'break_out' && $user->attendance_status === '休憩中') {
            if(!$attendance->break_out) {
                $attendance->break_out = Carbon::now()->format('H:i');
            }elseif(!$attendance->break2_out) {
                $attendance->break2_out = Carbon::now()->format('H:i');
            }
            $attendance->save();
            $user->update([
                'attendance_status' => '出勤中',
            ]);
        }
        return redirect('/attendance');
    }
    public function list(Request $request) {
        $user = Auth::user();
        $date = Carbon::parse($request->query('date', Carbon::now()));

        $nextMonth = $date->copy()->addMonth()->format('Y-m');
        $previousMonth = $date->copy()->subMonth()->format('Y-m');

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
                                            ->whereBetween('date', [$startOfMonth, $endOfMonth])
                                            ->orderBy('date', 'asc')
                                            ->get();
        
        $formattedAttendanceRecords = $attendanceRecords->map(function($attendance) {
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $date = Carbon::parse($attendance->date);
            $weekday = $weekdays[$date->dayOfWeek];
            return [
                'id' => $attendance->id,
                'date' => $date->format('m/d') . "($weekday)",
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : null,
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : null,
                'total_time' => $attendance->total_time,
                'total_break_time' => $attendance->total_break_time,
            ];
        });
        return view('user/user-attendance-list', compact('formattedAttendanceRecords', 'date', 'nextMonth', 'previousMonth'));
    }
    public function detail($id) {
        $attendanceRecord = AttendanceRecord::findOrFail($id);
        $user = User::findOrFail($attendanceRecord->user_id);
        $application = Application::where('attendance_record_id', $attendanceRecord->id)
                                  ->where('approval_status', '承認待ち')
                                  ->get();

        $applicationData = Application::where('attendance_record_id', $attendanceRecord->id)->first();

        $attendanceRecord = [
            'id' => $attendanceRecord->id,
            'year' => $attendanceRecord->date ? Carbon::parse($attendanceRecord->date)->format('Y年') : null,
            'date' => $attendanceRecord->date ? Carbon::parse($attendanceRecord->date)->format('m月d日') : null,
            'clock_in' => $attendanceRecord->clock_in ? Carbon::parse($attendanceRecord->clock_in)->format('H:i') :null,
            'clock_out' => $attendanceRecord->clock_out ? Carbon::parse($attendanceRecord->clock_out)->format('H:i') : null,
            'break_in' => $attendanceRecord->break_in ? Carbon::parse($attendanceRecord->break_in)->format('H:i') : null,
            'break_out' => $attendanceRecord->break_out ? Carbon::parse($attendanceRecord->break_out)->format('H:i') : null,
            'break2_in' => $attendanceRecord->break2_in ? Carbon::parse($attendanceRecord->break2_in)->format('H:i') : null,
            'break2_out' => $attendanceRecord->break2_out ? Carbon::parse($attendanceRecord->break2_out)->format('H:i') : null,
            'comment' => $attendanceRecord->comment,
        ];

        return view('user/user-detail', compact('user', 'attendanceRecord','application', 'applicationData'));
    }
    public function amendmentApplication(CorrectionRequest $request, $id) {
        $user = Auth::user();
        $attendance = AttendanceRecord::findOfFail($id);

        $application = new Application();
        $application->user_id = $user->id;
        $application->attendance_record_id = $attendance->id;
        $application->approval_status = '承認待ち';
        $application->application_date = now();
        $application->new_date = $attendance->date;
        $application->new_clock_in = $request->new_clock_in;
        $application->new_clock_out = $request->new_clock_out;
        if($request->new_clock_in) {
            $application->new_clock_in = $request->new_clock_in;
        }
        if($request->new_clock_out) {
            $application->new_clock_out = $request->new_clock_out;
        }
        if($request->new_clock2_in) {
            $application->new_clock2_in = $request->new_clock2_in;
        }
        if($request->new_clock2_out) {
            $application->new_clock2_out = $request->new_clock2_out;
        }
        $application->comment = $request->comment;

        $application->save();

        return redirect('/stamp_correction_request/list');
    }
    public function applicationList() {
        $user = Auth::user();
        $applications = Application::where('user_id', $user->id)->get();
        return view('user/user-application-list', compact('user', 'applications'));
    }
}

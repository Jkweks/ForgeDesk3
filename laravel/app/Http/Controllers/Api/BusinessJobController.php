<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BusinessJobController extends Controller
{
    /**
     * List all business jobs
     */
    public function index(Request $request)
    {
        try {
            $query = BusinessJob::query();

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by job number or name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('job_number', 'like', "%{$search}%")
                      ->orWhere('job_name', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }

            $jobs = $query
                ->with('createdBy')
                ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END")
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'job_number' => $job->job_number,
                        'job_name' => $job->job_name,
                        'customer_name' => $job->customer_name,
                        'status' => $job->status,
                        'status_label' => $job->status_label,
                        'start_date' => $job->start_date?->format('Y-m-d'),
                        'target_completion_date' => $job->target_completion_date?->format('Y-m-d'),
                        'days_until_completion' => $job->days_until_completion,
                        'created_by' => $job->createdBy ? [
                            'id' => $job->createdBy->id,
                            'name' => $job->createdBy->name,
                        ] : null,
                        'created_at' => $job->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'jobs' => $jobs,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list jobs', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to list jobs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed job information
     */
    public function show($id)
    {
        try {
            $job = BusinessJob::with([
                'createdBy',
                'doorFrameConfigurations',
                'jobReservations',
            ])->findOrFail($id);

            return response()->json([
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'customer_name' => $job->customer_name,
                    'site_address' => $job->site_address,
                    'contact_name' => $job->contact_name,
                    'contact_phone' => $job->contact_phone,
                    'contact_email' => $job->contact_email,
                    'status' => $job->status,
                    'status_label' => $job->status_label,
                    'start_date' => $job->start_date?->format('Y-m-d'),
                    'target_completion_date' => $job->target_completion_date?->format('Y-m-d'),
                    'actual_completion_date' => $job->actual_completion_date?->format('Y-m-d'),
                    'notes' => $job->notes,
                    'created_by' => $job->createdBy ? [
                        'id' => $job->createdBy->id,
                        'name' => $job->createdBy->name,
                    ] : null,
                    'configurations_count' => $job->doorFrameConfigurations->count(),
                    'reservations_count' => $job->jobReservations->count(),
                    'created_at' => $job->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $job->updated_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch job',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create new job
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'job_number' => 'required|string|max:100|unique:business_jobs,job_number',
                'job_name' => 'required|string|max:255',
                'customer_name' => 'nullable|string|max:255',
                'site_address' => 'nullable|string|max:500',
                'contact_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'contact_email' => 'nullable|email|max:255',
                'status' => 'nullable|in:active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'target_completion_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $job = BusinessJob::create([
                'job_number' => $request->job_number,
                'job_name' => $request->job_name,
                'customer_name' => $request->customer_name,
                'site_address' => $request->site_address,
                'contact_name' => $request->contact_name,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'status' => $request->status ?? 'active',
                'start_date' => $request->start_date,
                'target_completion_date' => $request->target_completion_date,
                'notes' => $request->notes,
                'created_by_id' => auth()->id(),
            ]);

            Log::info('Job created', [
                'job_id' => $job->id,
                'job_number' => $job->job_number,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Job created successfully',
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'status' => $job->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create job', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Failed to create job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing job
     */
    public function update(Request $request, $id)
    {
        try {
            $job = BusinessJob::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'job_number' => 'sometimes|required|string|max:100|unique:business_jobs,job_number,' . $id,
                'job_name' => 'sometimes|required|string|max:255',
                'customer_name' => 'nullable|string|max:255',
                'site_address' => 'nullable|string|max:500',
                'contact_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'contact_email' => 'nullable|email|max:255',
                'status' => 'nullable|in:active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'target_completion_date' => 'nullable|date',
                'actual_completion_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $job->update($request->only([
                'job_number',
                'job_name',
                'customer_name',
                'site_address',
                'contact_name',
                'contact_phone',
                'contact_email',
                'status',
                'start_date',
                'target_completion_date',
                'actual_completion_date',
                'notes',
            ]));

            Log::info('Job updated', [
                'job_id' => $job->id,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Job updated successfully',
                'job' => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'status' => $job->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete job (soft delete)
     */
    public function destroy($id)
    {
        try {
            $job = BusinessJob::findOrFail($id);

            // Check if job has configurations
            if ($job->doorFrameConfigurations()->count() > 0) {
                return response()->json([
                    'error' => 'Cannot delete job with existing configurations',
                    'message' => 'This job has ' . $job->doorFrameConfigurations()->count() . ' configuration(s). Please delete them first.',
                ], 422);
            }

            // Check if job has reservations
            if ($job->jobReservations()->count() > 0) {
                return response()->json([
                    'error' => 'Cannot delete job with existing reservations',
                    'message' => 'This job has ' . $job->jobReservations()->count() . ' reservation(s). Please remove them first.',
                ], 422);
            }

            $job->delete();

            Log::info('Job deleted', [
                'job_id' => $id,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Job deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete job', [
                'job_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

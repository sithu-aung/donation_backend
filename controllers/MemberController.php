<?php

namespace app\controllers;

use app\models\Account;
use app\models\Member;
use DateTime;
use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;

class MemberController extends BaseApiController
{
    public function actionIndex($page, $limit, $q = '', $blood_type = null, $status = null, $birth_year = null, $range_start = null, $range_end = null,
        // Individual search parameters for server-side filtering
        $phone = null, $father_name = null, $blood_bank_card = null,
        $member_id_search = null, $birth_date = null)
    {
        $query = Member::find();

        // Search by name, father_name, phone, blood_bank_card, or member_id
        if ($q) {
            $query->andWhere(['or',
                ['like', 'name', $q],
                ['like', 'father_name', $q],
                ['like', 'phone', $q],
                ['like', 'blood_bank_card', $q],
                ['like', 'member_id', $q],
            ]);
        }

        // Individual search filters (AND logic)
        if ($phone) {
            $query->andWhere(['like', 'phone', $phone]);
        }
        if ($father_name) {
            $query->andWhere(['like', 'father_name', $father_name]);
        }
        if ($blood_bank_card) {
            $query->andWhere(['like', 'blood_bank_card', $blood_bank_card]);
        }
        if ($member_id_search) {
            $query->andWhere(['like', 'member_id', $member_id_search]);
        }
        if ($birth_date) {
            $query->andWhere(['like', 'birth_date', $birth_date]);
        }

        // Filter by blood type
        if ($blood_type) {
            $query->andWhere(['blood_type' => $blood_type]);
        }

        // Filter by status
        if ($status) {
            $query->andWhere(['status' => $status]);
        }

        // Filter by birth year
        if ($birth_year) {
            $query->andWhere(['like', 'birth_date', $birth_year]);
        }

        // Range filtering - filter members by member_id range
        if ($range_start !== null && $range_end !== null) {
            $query->andWhere(['>=', 'member_id', $range_start])
                  ->andWhere(['<=', 'member_id', $range_end]);
        }

        // Apply pagination and ordering
        $queryClone = clone $query;
        $members = $query->with('donations')
                         ->offset($page * $limit)
                         ->limit($limit)
                         ->orderBy("member_id")
                         ->all();

        // Calculate total donation count for each member
        foreach ($members as $member) {
            $systemDonationCount = count($member->donations);
            $beforeCount = intval($member->member_count ?? 0);
            $totalCount = $beforeCount + $systemDonationCount;
            $member->total_count = strval($totalCount);
        }

        // Get the total count after applying filters
        $total = $queryClone->count();

        return $this->asJson([
            'status' => 'ok',
            'data' => $members,
            'total' => $total,
        ]);
    }

    /**
     * Honorable donors: members whose total blood-donation count
     * (legacy member_count + in-system donations) exceeds $min (default 30),
     * ordered most -> least.
     * GET /member/honorable-donors[?min=30]
     */
    public function actionHonorableDonors($min = 30)
    {
        $members = Member::find()->with('donations')->all();
        $result = [];
        foreach ($members as $member) {
            $systemDonationCount = count($member->donations);
            $beforeCount = intval($member->member_count ?? 0);
            $totalCount = $beforeCount + $systemDonationCount;
            if ($totalCount > intval($min)) {
                $member->total_count = strval($totalCount);
                $result[] = $member;
            }
        }

        usort($result, function ($a, $b) {
            return intval($b->total_count) - intval($a->total_count);
        });

        return $this->asJson([
            'status' => 'ok',
            'data' => $result,
            'total' => count($result),
        ]);
    }

    public function actionView($id)
    {
        // Check if the search parameter is numeric (likely an ID) or a string (likely a member_id)
        $query = Member::find()->with('donations');
        // if (is_numeric($id)) {
        //     $query->where(['id' => $id]);
        // } else {
        //     $query->where(['member_id' => $id]);
        // }
        $query->where(['id' => $id]);

        $member = $query->one();

        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
                'id' => $id
            ]);
        }

        // Calculate total donation count
        $systemDonationCount = count($member->donations);
        $beforeCount = intval($member->member_count ?? 0);
        $totalCount = $beforeCount + $systemDonationCount;
        
        // Update total_count in the member object
        $member->total_count = strval($totalCount);

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'member' => $member,
                'donations' => $member->donations,
                'donation_counts' => [
                    'before_joining' => $beforeCount,
                    'in_system' => $systemDonationCount,
                    'total' => $totalCount
                ]
            ],
        ]);
    }

    public function actionCreate()
    {
        $member = new Member();
        $request = Yii::$app->request;
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);

        // Generate member_id
        $totalMembers = Member::find()->count();
        $group = chr(65 + intval($totalMembers / 1000)); // Convert to letter A, B, C, etc.
        $number = str_pad(($totalMembers % 1000) + 1, 4, '0', STR_PAD_LEFT);
        $member->member_id = $group . '-' . $number;

        // Convert birth_date to 'd M Y' format
        if (isset($data['birth_date']) && !empty($data['birth_date'])) {
            $birthDateStr = $data['birth_date'];
            $date = null;
            
            // Try multiple date formats
            $formats = ['Y-m-d', 'd M Y', 'd MMM Y', 'j M Y', 'j MMM Y'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $birthDateStr);
                if ($date !== false) {
                    break;
                }
            }
            
            // If still no valid date, try PHP's strtotime
            if ($date === false || $date === null) {
                $timestamp = strtotime($birthDateStr);
                if ($timestamp !== false) {
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);
                }
            }
            
            $member->birth_date = $date ? $date->format('d M Y') : null;
        } else {
            $member->birth_date = null;
        }
        $member->blood_bank_card = $data['blood_bank_card'] ?? null;
        $member->blood_type = $data['blood_type'] ?? null;
        $member->father_name = $data['father_name'] ?? null;
        $member->member_count = $data['member_count'] ?? "0";
        $member->name = $data['name'] ?? null;
        $member->note = $data['note'] ?? null;
        $member->nrc = $data['nrc'] ?? null;
        $member->phone = $data['phone'] ?? null;
        $member->address = $data['address'] ?? null;
        $member->gender = $data['gender'] ?? null;
        $member->register_date = date('Y-m-d H:i:s');
        $member->total_count = "0";
        $member->status = 'available';
        $member->last_date = null;
        $member->owner_id = "1";

        if (!$member->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to create Member.',
                'errors' => $member->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $member
        ]);
    }

    public function actionUpdate($id)
    {
        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
            ]);
        }

        $request = Yii::$app->request;
        $rawBody = $request->getRawBody();
        $data = json_decode($rawBody, true);

        // Convert birth_date to 'd M Y' format
        if (isset($data['birth_date']) && !empty($data['birth_date'])) {
            $birthDateStr = $data['birth_date'];
            $date = null;
            
            // Try multiple date formats
            $formats = ['Y-m-d', 'd M Y', 'd MMM Y', 'j M Y', 'j MMM Y'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $birthDateStr);
                if ($date !== false) {
                    break;
                }
            }
            
            // If still no valid date, try PHP's strtotime
            if ($date === false || $date === null) {
                $timestamp = strtotime($birthDateStr);
                if ($timestamp !== false) {
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);
                }
            }
            
            $member->birth_date = $date ? $date->format('d M Y') : $member->birth_date;
        }
        $member->blood_bank_card = $data['blood_bank_card'] ?? $member->blood_bank_card;
        $member->blood_type = $data['blood_type'] ?? $member->blood_type;
        $member->father_name = $data['father_name'] ?? $member->father_name;
        $member->last_date = $data['last_date'] ?? $member->last_date;
        $member->member_count = $data['member_count'] ?? $member->member_count;
        $member->name = $data['name'] ?? $member->name;
        $member->note = $data['note'] ?? $member->note;
        $member->nrc = $data['nrc'] ?? $member->nrc;
        $member->phone = $data['phone'] ?? $member->phone;
        $member->address = $data['address'] ?? $member->address;
        $member->gender = $data['gender'] ?? $member->gender;
        $member->profile_url = $data['profile_url'] ?? $member->profile_url;
        $member->total_count = $data['total_count'] ?? $member->total_count;
        $member->status = $data['status'] ?? $member->status;

        if (!$member->save()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to update Member.',
                'errors' => $member->errors,
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => $member
        ]);
    }

    /**
     * Upload a member's profile photo to DigitalOcean Spaces and store the URL.
     * POST /member/upload-photo?id=<id>   (multipart/form-data, field "photo")
     */
    public function actionUploadPhoto($id)
    {
        // Require a valid bearer token (same scheme as BaseAuthController) — this
        // writes to a shared Spaces bucket, so it must not be anonymous.
        $authHeader = Yii::$app->request->headers->get('Authorization');
        $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : '';
        if (strlen($token) !== 64 || Account::findOne(['access_token' => $token]) === null) {
            Yii::$app->response->statusCode = 401;
            return $this->asJson(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson(['status' => 'error', 'message' => 'No Member Found.']);
        }

        $file = UploadedFile::getInstanceByName('photo')
            ?? UploadedFile::getInstanceByName('file');
        if ($file === null) {
            return $this->asJson(['status' => 'error', 'message' => 'No file uploaded.']);
        }
        if ($file->size > 8 * 1024 * 1024) {
            return $this->asJson(['status' => 'error', 'message' => 'File too large (max 8MB).']);
        }

        // Validate the actual content is a supported image (not just by
        // extension) to avoid MIME/type confusion.
        $info = @getimagesize($file->tempName);
        $mimeToExt = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp',
        ];
        $detectedMime = is_array($info) ? ($info['mime'] ?? '') : '';
        if (!isset($mimeToExt[$detectedMime])) {
            return $this->asJson(['status' => 'error', 'message' => 'Invalid or unsupported image file.']);
        }
        $ext = $mimeToExt[$detectedMime];
        $contentType = $detectedMime;
        $body = @file_get_contents($file->tempName);
        if ($body === false) {
            return $this->asJson(['status' => 'error', 'message' => 'Could not read uploaded file.']);
        }

        $cfg = Yii::$app->params['digitalocean']['spaces'] ?? [];
        $spaces = new \app\components\DoSpaces($cfg);
        if (!$spaces->isConfigured()) {
            return $this->asJson(['status' => 'error', 'message' => 'Upload service not configured.']);
        }

        $folder = rtrim($cfg['folder'] ?? 'redjuniors/members', '/');
        $key = $folder . '/member_' . $member->id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        try {
            $url = $spaces->putObject($key, $body, $contentType);
        } catch (\Exception $e) {
            Yii::error('Spaces upload failed: ' . $e->getMessage(), 'upload');
            return $this->asJson(['status' => 'error', 'message' => 'Upload failed. Please try again.']);
        }

        $member->profile_url = $url;
        $member->save(false, ['profile_url']);

        return $this->asJson([
            'status' => 'ok',
            'url' => $url,
            'data' => $member,
        ]);
    }

    public function actionDelete($id)
    {
        $member = Member::findOne($id);
        if ($member === null) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'No Member Found.',
            ]);
        }
        if (!$member->delete()) {
            return $this->asJson([
                'status' => 'error',
                'message' => 'Failed to delete Member.',
            ]);
        }

        return $this->asJson([
            'status' => 'ok',
            'message' => 'Member is deleted.'
        ]);
    }

    public function actionCheckExists($name, $father_name = null, $blood_type = null)
    {
        $query = Member::find();

        // Add name condition (required)
        $query->andWhere(['like', 'name', $name]);

        // Add father_name condition if provided
        if ($father_name) {
            $query->andWhere(['like', 'father_name', $father_name]);
        }

        // Add blood_type condition if provided
        if ($blood_type) {
            $query->andWhere(['blood_type' => $blood_type]);
        }

        // Find matching members
        $members = $query->all();

        return $this->asJson([
            'status' => 'ok',
            'exists' => count($members) > 0,
            'members' => $members,
        ]);
    }

    /**
     * Returns pre-calculated range options for member list dropdown
     * GET /member/ranges?blood_type=A (Rh +)
     */
    public function actionRanges($blood_type = null)
    {
        $query = Member::find()->select('member_id');

        if ($blood_type) {
            $query->andWhere(['blood_type' => $blood_type]);
        }

        // Get all member_ids sorted alphanumerically
        $memberIds = $query
            ->orderBy(['member_id' => SORT_ASC])
            ->column();

        $rangeSize = 50;
        $ranges = [];
        $totalMembers = count($memberIds);

        for ($i = 0; $i < $totalMembers; $i += $rangeSize) {
            $endIndex = min($i + $rangeSize - 1, $totalMembers - 1);
            $startId = $memberIds[$i];
            $endId = $memberIds[$endIndex];

            $ranges[] = [
                'start' => $startId,
                'end' => $endId,
                'label' => "{$startId} မှ {$endId}",
                'count' => $endIndex - $i + 1,
            ];
        }

        return $this->asJson([
            'status' => 'ok',
            'data' => [
                'ranges' => $ranges,
                'totalMembers' => $totalMembers,
            ],
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSms extends Model
{
    protected $table = 'member_sms';

    protected $fillable = ['user_id', 'body', 'audience', 'ssc_batch', 'recipient_count', 'parts', 'request_ids'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Same groups as member emails ({@see MemberEmail::AUDIENCES}), limited to members with a mobile number.
     *
     * @return Builder<User>
     */
    public static function recipients(string $audience, ?int $sscBatch = null): Builder
    {
        return MemberEmail::recipients($audience, $sscBatch)->whereNotNull('phone')->where('phone', '!=', '');
    }

    /**
     * How many SMS a message costs per person: 160 characters (153 per part when split) for plain English,
     * 70 (67 per part) as soon as it has Bangla or other non-ASCII text. Mirrors smsParts() in resources/js/app.js.
     */
    public static function parts(string $message): int
    {
        $length = mb_strlen($message);
        [$single, $multi] = preg_match('/^[\x00-\x7F]*$/', $message) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}

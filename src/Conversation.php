<?php

namespace Neoncube\FlarumPrivateMessages;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

class Conversation extends AbstractModel
{
    protected $table = 'conversations';

    public $timestamps = true;

    public $fillable = [
        'user_one_id',
        'user_two_id',
        'status',
    ];

    protected $dates = ['created_at', 'updated_at'];

    /** 新增：默认把 status 设为合法 JSON，防止新纪录出现 NULL */
    protected $attributes = [
        'status' => '[]',
    ];

    /** 新增：写入兜底，避免把 NULL/空串写进 DB；数组/对象自动转 JSON 字符串 */
    public function setStatusAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['status'] = '[]';
            return;
        }

        if (is_array($value) || is_object($value)) {
            $this->attributes['status'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->attributes['status'] = (string) $value;
    }

    /** 可选：读取兜底，旧数据若为 NULL/空串也返回合法 JSON 字符串 */
    public function getStatusAttribute($value): string
    {
        return ($value === null || $value === '') ? '[]' : (string) $value;
    }

    public static function start()
    {
        $conversation = new static;
        $conversation->created_at = Carbon::now();
        return $conversation;
    }

    public static function findOrFail($id)
    {
        $query = static::where('id', $id);
        return $query->firstOrFail();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id')
            ->with('user');
    }

    public function recipients()
    {
        return $this->hasMany(ConversationUser::class, 'conversation_id')
            ->with('user');
    }
}


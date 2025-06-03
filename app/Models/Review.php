<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'comment',
        'is_verified',
        'is_approved',
        'helpful_votes',
        'helpful_count',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'is_verified' => 'boolean',
        'is_approved' => 'boolean',
        'helpful_votes' => 'array',
        'helpful_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $with = ['user']; // Always load user relationship

    /**
     * Get the product that owns the review.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user that owns the review.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for approved reviews only.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for verified purchase reviews.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for reviews with specific rating.
     */
    public function scopeWithRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for reviews ordered by helpfulness.
     */
    public function scopeOrderByHelpfulness(Builder $query): Builder
    {
        return $query->orderBy('helpful_count', 'desc');
    }

    /**
     * Scope for recent reviews.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if a user found this review helpful.
     */
    public function isHelpfulFor(int $userId): bool
    {
        $helpfulVotes = $this->helpful_votes ?? [];
        return in_array($userId, $helpfulVotes);
    }

    /**
     * Mark review as helpful by a user.
     */
    public function markHelpful(int $userId): bool
    {
        $helpfulVotes = $this->helpful_votes ?? [];

        if (!in_array($userId, $helpfulVotes)) {
            $helpfulVotes[] = $userId;
            $this->helpful_votes = $helpfulVotes;
            $this->helpful_count = count($helpfulVotes);
            return $this->save();
        }

        return false;
    }

    /**
     * Unmark review as helpful by a user.
     */
    public function unmarkHelpful(int $userId): bool
    {
        $helpfulVotes = $this->helpful_votes ?? [];
        $key = array_search($userId, $helpfulVotes);

        if ($key !== false) {
            unset($helpfulVotes[$key]);
            $this->helpful_votes = array_values($helpfulVotes);
            $this->helpful_count = count($this->helpful_votes);
            return $this->save();
        }

        return false;
    }

    /**
     * Get formatted rating with stars.
     */
    public function getRatingStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Get review age in human readable format.
     */
    public function getAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Update product rating when review is created
        static::created(function ($review) {
            $review->product->updateRating();
        });

        // Update product rating when review is updated
        static::updated(function ($review) {
            $review->product->updateRating();
        });

        // Update product rating when review is deleted
        static::deleted(function ($review) {
            $review->product->updateRating();
        });
    }
}

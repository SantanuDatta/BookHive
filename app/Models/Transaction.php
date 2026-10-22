<?php

namespace App\Models;

use App\Enums\BorrowedStatus;
use App\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property-read User|null $user
 * @property-read Book|null $book
 * @property BorrowedStatus|null $status
 */
#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'borrowed_date',
        'borrowed_for',
        'returned_date',
        'status',
        'fine',
    ];

    protected $casts = [
        'status' => BorrowedStatus::class,
        'borrowed_date' => 'date',
        'returned_date' => 'date',
        'fine' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function booted(): void
    {
        parent::boot();

        static::saving(function (self $transaction): void {
            if ($transaction->returned_date === null) {
                $transaction->fine = 0;

                return;
            }

            $borrowedDate = Carbon::parse($transaction->borrowed_date);
            $borrowedFor = (int) $transaction->borrowed_for;
            $returnDate = Carbon::parse($transaction->returned_date);
            $dueDate = $borrowedDate->addDays($borrowedFor);
            $delay = 0;
            $fine = 0;
            if ($returnDate->gt($dueDate)) {
                $delay = $dueDate->diffInDays($returnDate);
                $fine = $delay * 10;
            }
            $transaction->fine = $fine;
        });

        static::deleting(function (self $transaction): void {
            if ($transaction->getRawOriginal('status') === BorrowedStatus::Borrowed->value) {
                self::releaseBook((int) $transaction->book_id);
            }
        });
    }

    public static function reserveBook(int $bookId): void
    {
        $book = Book::query()->lockForUpdate()->find($bookId);

        if ($book === null || ! $book->available || $book->stock < 1) {
            throw ValidationException::withMessages([
                'book_id' => 'The selected book is no longer available.',
            ]);
        }

        $remainingStock = $book->stock - 1;

        $book->update([
            'stock' => $remainingStock,
            'available' => $remainingStock > 0,
        ]);
    }

    public static function releaseBook(int $bookId): void
    {
        $book = Book::query()->lockForUpdate()->find($bookId);

        if ($book === null) {
            return;
        }

        $book->update([
            'stock' => $book->stock + 1,
            'available' => true,
        ]);
    }

    public static function synchronizeBookInventory(EloquentModel $transaction, array $data): void
    {
        if (! $transaction instanceof self) {
            return;
        }

        $oldBookId = (int) $transaction->getRawOriginal('book_id');
        $newBookId = (int) $data['book_id'];
        $wasBorrowed = $transaction->getRawOriginal('status') === BorrowedStatus::Borrowed->value;
        $isBorrowed = self::statusValue($data['status'] ?? null) === BorrowedStatus::Borrowed->value;
        $bookChanged = $oldBookId !== $newBookId;

        if ($wasBorrowed && (! $isBorrowed || $bookChanged)) {
            self::releaseBook($oldBookId);
        }

        if ($isBorrowed && (! $wasBorrowed || $bookChanged)) {
            self::reserveBook($newBookId);
        }
    }

    private static function statusValue(mixed $status): ?string
    {
        return $status instanceof BorrowedStatus ? $status->value : (is_string($status) ? $status : null);
    }
}

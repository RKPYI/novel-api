<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactResponseReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public $contact;

    /**
     * Create a new notification instance.
     */
    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Response to Your Contact Message - ' . config('app.name'))
            ->greeting('Hello ' . $this->contact->name . '!')
            ->line('We have responded to your contact message regarding: ' . $this->contact->subject)
            ->line('Our response:')
            ->line($this->contact->admin_response)
            ->line('If you have any further questions, please feel free to contact us again.')
            ->salutation('Regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_id' => $this->contact->id,
            'type' => 'contact_response',
            'title' => 'Response to Your Contact Message',
            'message' => sprintf(
                'We have responded to your message: %s',
                $this->contact->subject
            ),
            'data' => [
                'contact_id' => $this->contact->id,
                'subject' => $this->contact->subject,
                'admin_response' => $this->contact->admin_response,
                'responded_at' => $this->contact->responded_at,
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}

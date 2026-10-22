<?php

namespace App\Filament\Staff\Pages\Auth;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getAvatarFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getAddressFormComponent(),
                $this->getPhoneFormComponent(),
            ])
            ->operation('edit')
            ->model($this->getUser())
            ->statePath('data');
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label('Upload Avatar')
            ->image()
            ->imageEditor()
            ->avatar()
            ->directory('users')
            ->deleteUploadedFileUsing(fn (string $file): bool => Storage::disk('public')
                ->delete($file))
            ->extraAttributes([
                'class' => 'justify-center',
            ]);
    }

    protected function getAddressFormComponent(): Component
    {
        return TextInput::make('address');
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->tel();
    }
}

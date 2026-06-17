<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoginAttemptResource\Pages;
use App\Filament\Resources\LoginAttemptResource\RelationManagers;
use App\Modules\Accounts\Models\LoginAttempt;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoginAttemptResource extends Resource
{
    protected static ?string $model = LoginAttempt::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('user_agent')
                    ->maxLength(65535),
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('failure_reason')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address'),
                Tables\Columns\TextColumn::make('user_agent'),
                Tables\Columns\TextColumn::make('username'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('failure_reason'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoginAttempts::route('/'),
            'create' => Pages\CreateLoginAttempt::route('/create'),
            'edit' => Pages\EditLoginAttempt::route('/{record}/edit'),
        ];
    }    
}

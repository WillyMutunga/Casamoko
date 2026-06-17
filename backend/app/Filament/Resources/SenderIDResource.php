<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SenderIDResource\Pages;
use App\Filament\Resources\SenderIDResource\RelationManagers;
use App\Modules\Messaging\Models\SenderID;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SenderIDResource extends Resource
{
    protected static ?string $model = SenderID::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id')
                    ->required(),
                Forms\Components\TextInput::make('fallback_sender_id_id'),
                Forms\Components\TextInput::make('sender_id')
                    ->maxLength(30),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('fallback_sender_id_id'),
                Tables\Columns\TextColumn::make('sender_id'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListSenderIDS::route('/'),
            'create' => Pages\CreateSenderID::route('/create'),
            'edit' => Pages\EditSenderID::route('/{record}/edit'),
        ];
    }    
}

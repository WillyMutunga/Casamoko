<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncomingMessageResource\Pages;
use App\Filament\Resources\IncomingMessageResource\RelationManagers;
use App\Modules\Messaging\Models\IncomingMessage;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IncomingMessageResource extends Resource
{
    protected static ?string $model = IncomingMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('shortcode_id')
                    ->required(),
                Forms\Components\TextInput::make('keyword_id'),
                Forms\Components\TextInput::make('msisdn')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('msisdn_hash')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->maxLength(65535),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shortcode_id'),
                Tables\Columns\TextColumn::make('keyword_id'),
                Tables\Columns\TextColumn::make('msisdn'),
                Tables\Columns\TextColumn::make('msisdn_hash'),
                Tables\Columns\TextColumn::make('message'),
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
            'index' => Pages\ListIncomingMessages::route('/'),
            'create' => Pages\CreateIncomingMessage::route('/create'),
            'edit' => Pages\EditIncomingMessage::route('/{record}/edit'),
        ];
    }    
}

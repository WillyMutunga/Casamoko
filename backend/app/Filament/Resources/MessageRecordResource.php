<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageRecordResource\Pages;
use App\Filament\Resources\MessageRecordResource\RelationManagers;
use App\Modules\Messaging\Models\MessageRecord;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MessageRecordResource extends Resource
{
    protected static ?string $model = MessageRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('campaign_id'),
                Forms\Components\TextInput::make('route_id'),
                Forms\Components\TextInput::make('contact_id'),
                Forms\Components\TextInput::make('msisdn_hash')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mno_message_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('network_status_code')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign_id'),
                Tables\Columns\TextColumn::make('route_id'),
                Tables\Columns\TextColumn::make('contact_id'),
                Tables\Columns\TextColumn::make('msisdn_hash'),
                Tables\Columns\TextColumn::make('price'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('mno_message_id'),
                Tables\Columns\TextColumn::make('network_status_code'),
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
            'index' => Pages\ListMessageRecords::route('/'),
            'create' => Pages\CreateMessageRecord::route('/create'),
            'edit' => Pages\EditMessageRecord::route('/{record}/edit'),
        ];
    }    
}

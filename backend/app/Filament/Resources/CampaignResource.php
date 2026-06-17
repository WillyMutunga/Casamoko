<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Resources\CampaignResource\RelationManagers;
use App\Modules\Messaging\Models\Campaign;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id')
                    ->required(),
                Forms\Components\TextInput::make('sender_id_id'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('template')
                    ->required()
                    ->maxLength(65535),
                Forms\Components\TextInput::make('unicode_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sent_count')
                    ->required(),
                Forms\Components\TextInput::make('delivered_count')
                    ->required(),
                Forms\Components\TextInput::make('failed_count')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('scheduled_at'),
                Forms\Components\TextInput::make('tps_limit')
                    ->required(),
                Forms\Components\TextInput::make('quiet_hours_start')
                    ->maxLength(255),
                Forms\Components\TextInput::make('quiet_hours_end')
                    ->maxLength(255),
                Forms\Components\TextInput::make('recurring_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('approval_status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_ab_test')
                    ->required(),
                Forms\Components\Textarea::make('template_b')
                    ->maxLength(65535),
                Forms\Components\TextInput::make('ab_split_ratio')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('sender_id_id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('template'),
                Tables\Columns\TextColumn::make('unicode_type'),
                Tables\Columns\TextColumn::make('sent_count'),
                Tables\Columns\TextColumn::make('delivered_count'),
                Tables\Columns\TextColumn::make('failed_count'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('tps_limit'),
                Tables\Columns\TextColumn::make('quiet_hours_start'),
                Tables\Columns\TextColumn::make('quiet_hours_end'),
                Tables\Columns\TextColumn::make('recurring_type'),
                Tables\Columns\TextColumn::make('approval_status'),
                Tables\Columns\IconColumn::make('is_ab_test')
                    ->boolean(),
                Tables\Columns\TextColumn::make('template_b'),
                Tables\Columns\TextColumn::make('ab_split_ratio'),
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
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }    
}

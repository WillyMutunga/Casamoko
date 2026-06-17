<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RouteResource\Pages;
use App\Filament\Resources\RouteResource\RelationManagers;
use App\Modules\Messaging\Models\Route;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('carrier_id')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('destination_network')
                    ->maxLength(255),
                Forms\Components\TextInput::make('bind_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('window_size')
                    ->required(),
                Forms\Components\Toggle::make('use_tls')
                    ->required(),
                Forms\Components\TextInput::make('delivery_rate_score')
                    ->required(),
                Forms\Components\TextInput::make('latency_score')
                    ->required(),
                Forms\Components\TextInput::make('cost_per_sms')
                    ->required(),
                Forms\Components\TextInput::make('tps_limit')
                    ->required(),
                Forms\Components\TextInput::make('priority')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('carrier_id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('destination_network'),
                Tables\Columns\TextColumn::make('bind_type'),
                Tables\Columns\TextColumn::make('window_size'),
                Tables\Columns\IconColumn::make('use_tls')
                    ->boolean(),
                Tables\Columns\TextColumn::make('delivery_rate_score'),
                Tables\Columns\TextColumn::make('latency_score'),
                Tables\Columns\TextColumn::make('cost_per_sms'),
                Tables\Columns\TextColumn::make('tps_limit'),
                Tables\Columns\TextColumn::make('priority'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
            'index' => Pages\ListRoutes::route('/'),
            'create' => Pages\CreateRoute::route('/create'),
            'edit' => Pages\EditRoute::route('/{record}/edit'),
        ];
    }    
}

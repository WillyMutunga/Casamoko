<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OptOutRegistryResource\Pages;
use App\Filament\Resources\OptOutRegistryResource\RelationManagers;
use App\Modules\Messaging\Models\OptOutRegistry;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OptOutRegistryResource extends Resource
{
    protected static ?string $model = OptOutRegistry::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id')
                    ->required(),
                Forms\Components\TextInput::make('msisdn')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('msisdn_hash')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('msisdn'),
                Tables\Columns\TextColumn::make('msisdn_hash'),
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
            'index' => Pages\ListOptOutRegistries::route('/'),
            'create' => Pages\CreateOptOutRegistry::route('/create'),
            'edit' => Pages\EditOptOutRegistry::route('/{record}/edit'),
        ];
    }    
}

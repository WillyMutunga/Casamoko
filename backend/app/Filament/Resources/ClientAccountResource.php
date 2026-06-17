<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientAccountResource\Pages;
use App\Filament\Resources\ClientAccountResource\RelationManagers;
use App\Modules\Accounts\Models\ClientAccount;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientAccountResource extends Resource
{
    protected static ?string $model = ClientAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('reseller_account_id'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('billing_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('low_balance_threshold')
                    ->required(),
                Forms\Components\TextInput::make('wallet_balance')
                    ->required(),
                Forms\Components\TextInput::make('credit_limit')
                    ->required(),
                Forms\Components\TextInput::make('kyc_metadata'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reseller_account_id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('billing_type'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('low_balance_threshold'),
                Tables\Columns\TextColumn::make('wallet_balance'),
                Tables\Columns\TextColumn::make('credit_limit'),
                Tables\Columns\TextColumn::make('kyc_metadata'),
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
            'index' => Pages\ListClientAccounts::route('/'),
            'create' => Pages\CreateClientAccount::route('/create'),
            'edit' => Pages\EditClientAccount::route('/{record}/edit'),
        ];
    }    
}

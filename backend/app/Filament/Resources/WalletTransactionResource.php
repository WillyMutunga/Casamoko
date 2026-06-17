<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages;
use App\Filament\Resources\WalletTransactionResource\RelationManagers;
use App\Modules\Finance\Models\WalletTransaction;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id')
                    ->required(),
                Forms\Components\TextInput::make('adjusted_by_user_id'),
                Forms\Components\TextInput::make('amount')
                    ->required(),
                Forms\Components\TextInput::make('balance_after')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_id'),
                Forms\Components\TextInput::make('reason_code')
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('adjusted_by_user_id'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('balance_after'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('reference_type'),
                Tables\Columns\TextColumn::make('reference_id'),
                Tables\Columns\TextColumn::make('reason_code'),
                Tables\Columns\TextColumn::make('description'),
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
            'index' => Pages\ListWalletTransactions::route('/'),
            'create' => Pages\CreateWalletTransaction::route('/create'),
            'edit' => Pages\EditWalletTransaction::route('/{record}/edit'),
        ];
    }    
}

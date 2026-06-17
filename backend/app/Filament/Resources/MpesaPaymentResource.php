<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MpesaPaymentResource\Pages;
use App\Filament\Resources\MpesaPaymentResource\RelationManagers;
use App\Modules\Finance\Models\MpesaPayment;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MpesaPaymentResource extends Resource
{
    protected static ?string $model = MpesaPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id')
                    ->required(),
                Forms\Components\TextInput::make('merchant_request_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('checkout_request_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mpesa_receipt_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->required(),
                Forms\Components\TextInput::make('phone_number')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('response_metadata'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('merchant_request_id'),
                Tables\Columns\TextColumn::make('checkout_request_id'),
                Tables\Columns\TextColumn::make('mpesa_receipt_number'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('phone_number'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('response_metadata'),
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
            'index' => Pages\ListMpesaPayments::route('/'),
            'create' => Pages\CreateMpesaPayment::route('/create'),
            'edit' => Pages\EditMpesaPayment::route('/{record}/edit'),
        ];
    }    
}

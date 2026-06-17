<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerAccountResource\Pages;
use App\Filament\Resources\ResellerAccountResource\RelationManagers;
use App\Modules\Accounts\Models\ResellerAccount;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ResellerAccountResource extends Resource
{
    protected static ?string $model = ResellerAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('markup_percentage')
                    ->required(),
                Forms\Components\TextInput::make('api_credits')
                    ->required(),
                Forms\Components\TextInput::make('billing_config'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('markup_percentage'),
                Tables\Columns\TextColumn::make('api_credits'),
                Tables\Columns\TextColumn::make('billing_config'),
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
            'index' => Pages\ListResellerAccounts::route('/'),
            'create' => Pages\CreateResellerAccount::route('/create'),
            'edit' => Pages\EditResellerAccount::route('/{record}/edit'),
        ];
    }    
}

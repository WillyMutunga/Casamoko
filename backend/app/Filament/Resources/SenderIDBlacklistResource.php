<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SenderIDBlacklistResource\Pages;
use App\Filament\Resources\SenderIDBlacklistResource\RelationManagers;
use App\Modules\Messaging\Models\SenderIDBlacklist;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SenderIDBlacklistResource extends Resource
{
    protected static ?string $model = SenderIDBlacklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pattern')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reason')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pattern'),
                Tables\Columns\TextColumn::make('reason'),
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
            'index' => Pages\ListSenderIDBlacklists::route('/'),
            'create' => Pages\CreateSenderIDBlacklist::route('/create'),
            'edit' => Pages\EditSenderIDBlacklist::route('/{record}/edit'),
        ];
    }    
}

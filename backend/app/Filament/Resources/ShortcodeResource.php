<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShortcodeResource\Pages;
use App\Filament\Resources\ShortcodeResource\RelationManagers;
use App\Modules\Messaging\Models\Shortcode;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShortcodeResource extends Resource
{
    protected static ?string $model = Shortcode::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('client_account_id'),
                Forms\Components\TextInput::make('shortcode')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_dedicated')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_account_id'),
                Tables\Columns\TextColumn::make('shortcode'),
                Tables\Columns\IconColumn::make('is_dedicated')
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
            'index' => Pages\ListShortcodes::route('/'),
            'create' => Pages\CreateShortcode::route('/create'),
            'edit' => Pages\EditShortcode::route('/{record}/edit'),
        ];
    }    
}

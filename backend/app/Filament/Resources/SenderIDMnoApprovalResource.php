<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SenderIDMnoApprovalResource\Pages;
use App\Filament\Resources\SenderIDMnoApprovalResource\RelationManagers;
use App\Modules\Messaging\Models\SenderIDMnoApproval;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SenderIDMnoApprovalResource extends Resource
{
    protected static ?string $model = SenderIDMnoApproval::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sender_id_id')
                    ->required(),
                Forms\Components\TextInput::make('mno_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('reason')
                    ->maxLength(65535),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender_id_id'),
                Tables\Columns\TextColumn::make('mno_name'),
                Tables\Columns\TextColumn::make('status'),
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
            'index' => Pages\ListSenderIDMnoApprovals::route('/'),
            'create' => Pages\CreateSenderIDMnoApproval::route('/create'),
            'edit' => Pages\EditSenderIDMnoApproval::route('/{record}/edit'),
        ];
    }    
}

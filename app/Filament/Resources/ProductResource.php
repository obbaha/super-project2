<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Stock;

class ProductResource extends Resource
{
    protected static ?int $navigationSort = 2;

public static function getNavigationGroup(): ?string
{
    return __('Stock');
}

public static function getModelLabel(): string
{
    return __('Product');
}

public static function getPluralModelLabel(): string
{
    return __('Products');
}

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'Name')
                    ->label(__('Category'))
                    ->required(),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label(__('Name'))
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->label(__('Price'))
                    ->required()
                    ->numeric()
                    ->prefix('SYP'),
                Forms\Components\TextInput::make('old_price')
                    ->label(__('Old Price'))
                    ->numeric()
                    ->prefix('SYP'),
                Forms\Components\Toggle::make('is_available')
                    ->label(__('Is Available'))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_price')
                    ->label(__('Old Price'))
                    ->money('SYP')
                    ->color('danger') // لون أحمر للتنبيه
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->label(__('Is_Available'))
                    ->boolean()
                    ->default(true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

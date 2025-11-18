<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class Clipboard extends Component
{
    /**
     * @param  string  $text  Text to be copied to clipboard
     * @param  string|null  $label  Optional label to display on the button
     * @param  bool  $isButton  Whether to render as a button or span
     * @param  string|null  $buttonSize  Size of button (sm, lg, etc.)
     * @param  string  $buttonVariant  Bootstrap button variant for normal state
     * @param  string  $successVariant  Bootstrap button variant for success state
     * @param  string  $iconPosition  Position of the icon relative to the text
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $text = '',
        public ?string $label = null,
        public bool $isButton = true,
        public ?string $buttonSize = null,
        public string $buttonVariant = 'outline-secondary',
        public string $successVariant = 'outline-success',
        public string $iconPosition = 'left',
    ) {
        if (blank($this->text)) {
            throw new InvalidArgumentException('Text cannot be blank.');
        }

        $this->validateVariant($this->buttonVariant);
        $this->validateVariant($this->successVariant);
        $this->validateIconPosition($this->iconPosition);

        if ($this->buttonSize !== null) {
            $this->validateSize($this->buttonSize);
        }
    }

    /**
     * Validate the button variant is a valid Bootstrap variant.
     *
     * @throws InvalidArgumentException
     */
    private function validateVariant(string $variant): void
    {
        $validVariants = [
            'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'link',
            'outline-primary', 'outline-secondary', 'outline-success', 'outline-danger',
            'outline-warning', 'outline-info', 'outline-light', 'outline-dark',
        ];

        if (! in_array($variant, $validVariants)) {
            throw new InvalidArgumentException(
                "Invalid button variant '{$variant}'. Must be one of: " . implode(', ', $validVariants)
            );
        }
    }

    /**
     * Validate the button size is a valid Bootstrap size.
     *
     * @throws InvalidArgumentException
     */
    private function validateSize(string $size): void
    {
        $validSizes = ['sm', 'lg'];

        if (! in_array($size, $validSizes)) {
            throw new InvalidArgumentException(
                "Invalid button size '{$size}'. Must be one of: " . implode(', ', $validSizes)
            );
        }
    }

    /**
     * Validate the icon position is valid.
     *
     * @throws InvalidArgumentException
     */
    private function validateIconPosition(string $position): void
    {
        $validPositions = ['left', 'right', 'none'];

        if (! in_array($position, $validPositions)) {
            throw new InvalidArgumentException(
                "Invalid icon position '{$position}'. Must be one of: " . implode(', ', $validPositions)
            );
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('components.clipboard');
    }
}

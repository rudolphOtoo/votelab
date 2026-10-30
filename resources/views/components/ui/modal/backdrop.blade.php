@aware(['backdrop' => 'blur'])
<div 
    x-show="isOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @class([
        'fixed inset-0 transition',
        'bg-black/30 backdrop-blur-[1px]' => $backdrop === 'blur',
        'bg-black/50' => $backdrop === 'dark', 
        'bg-transparent' => $backdrop === 'transparent',
    ])
></div>
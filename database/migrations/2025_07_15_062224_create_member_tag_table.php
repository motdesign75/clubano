<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->primary(['member_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tag');
    }
};

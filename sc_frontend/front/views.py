import os
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.http import HttpResponse, Http404
from django.shortcuts import render_to_response, get_object_or_404
from django.template import RequestContext
from django.core.exceptions import ObjectDoesNotExist
from django.utils.encoding import smart_str
from django.views.decorators.http import require_POST
from django_ajax.decorators import ajax
from front.confirmation_email import send_confirmation_email, check_confirmation
from front.models import Camera, Image, EmailAddress
from sc_frontend import settings


@login_required
def index(request):
    cameras = Camera.objects.filter(user=request.user)
    context = RequestContext(request, {
        'cameras': cameras,
    })
    return render_to_response('front/index.html', context)


@login_required
def latest_image(request, camera_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    try:
        image = Image.objects.filter(camera=camera).latest('received')
    except ObjectDoesNotExist:
        raise Http404("Image not found.")

    filename = '%04d-%s.jpg' % (int(camera_id), image.received.strftime("%Y%m%d-%H%M%S"))
    filepath = os.path.join(settings.IMAGE_DIR, filename)
    response = HttpResponse(content_type='image/jpeg')
    response['X-Sendfile'] = smart_str(filepath)
    return response


@login_required
@require_POST
@ajax
def delete_mail_forward(request, camera_id, address_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    address = camera.email_addresses.get(id=address_id)
    camera.email_addresses.remove(address)


@login_required
@require_POST
@ajax
def add_mail_forward(request, camera_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    name = request.POST['name']
    email = request.POST['email']
    try:
        address = EmailAddress.objects.get(user=request.user, address=email.lower())
        print "Address ({}) already exists. Overwriting name.".format(email.lower(), )
        address.name = name
        address.save()
    except ObjectDoesNotExist:
        address = EmailAddress(user=request.user, address=email.lower(), name=name)
        address.save()
    if not address.verified:
        send_confirmation_email(request, camera, email, address.id)
        print "Address not verified."
    camera.email_addresses.add(address)
    return None


@login_required
def mail_forwards(request, camera_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    context = RequestContext(request, {
        'forwards': camera.email_addresses,
    })
    return render_to_response('front/forwards.html', context)


@login_required
@require_POST
@ajax
def update_camera_name(request, camera_id):
    name = request.POST['value']
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    camera.name = name
    camera.save()
    return None


def confirm_email(request, token):
    address = check_confirmation(token)
    context = RequestContext(request, {
        'address': address,
    })
    if address is None:
        messages.error(request, 'Etwas ist schiefgegangen!')
        return render_to_response('front/confirmation_error.html', context)
    else:
        return render_to_response('front/confirmation_success.html', context)


